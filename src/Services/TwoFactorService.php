<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Illuminate\Support\Str;
use RuntimeException;

class TwoFactorService
{
    /**
     * Generate a new TOTP secret and backup codes for a user.
     * Returns data for the setup response — does NOT save yet.
     */
    public function generateSetup(object $user): array
    {
        $this->requirePragmaPackage();

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret    = $google2fa->generateSecretKey();

        $backupCodes = $this->generateBackupCodes();

        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'App'),
            $user->email,
            $secret
        );

        return [
            'secret'       => $secret,
            'qr_url'       => $qrUrl,
            'backup_codes' => $backupCodes,
        ];
    }

    /**
     * Confirm setup — validate the first TOTP code, then persist.
     */
    public function confirmSetup(object $user, string $secret, string $totpCode, array $backupCodes): bool
    {
        $this->requirePragmaPackage();

        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        if (!$google2fa->verifyKey($secret, $totpCode)) {
            return false;
        }

        $user->update([
            'two_factor_enabled'      => true,
            'two_factor_secret'       => encrypt($secret),
            'two_factor_backup_codes' => encrypt(json_encode(
                array_map(fn ($c) => bcrypt($c), $backupCodes)
            )),
        ]);

        UserAdminEvents::fire(UserAdminEvents::TFA_ENABLED, [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        return true;
    }

    /**
     * Verify a TOTP code or backup code for a user.
     * Returns false on failure; 'totp' or 'backup' on success.
     */
    public function verify(object $user, ?string $totpCode, ?string $backupCode): string|false
    {
        // Try backup code first
        if ($backupCode) {
            return $this->verifyBackupCode($user, $backupCode) ? 'backup' : false;
        }

        if ($totpCode) {
            return $this->verifyTotp($user, $totpCode) ? 'totp' : false;
        }

        return false;
    }

    public function disable(object $user): void
    {
        $user->update([
            'two_factor_enabled'      => false,
            'two_factor_secret'       => null,
            'two_factor_backup_codes' => null,
        ]);

        UserAdminEvents::fire(UserAdminEvents::TFA_DISABLED, [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);
    }

    public function regenerateBackupCodes(object $user): array
    {
        $codes = $this->generateBackupCodes();

        $user->update([
            'two_factor_backup_codes' => encrypt(json_encode(
                array_map(fn ($c) => bcrypt($c), $codes)
            )),
        ]);

        return $codes;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function verifyTotp(object $user, string $code): bool
    {
        $this->requirePragmaPackage();

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        return (bool) $google2fa->verifyKey($secret, $code);
    }

    private function verifyBackupCode(object $user, string $code): bool
    {
        if (!$user->two_factor_backup_codes) {
            return false;
        }

        $hashed = json_decode(decrypt($user->two_factor_backup_codes), true);

        foreach ($hashed as $index => $hash) {
            if (password_verify($code, $hash)) {
                // Consume the code (one-time use)
                unset($hashed[$index]);
                $user->update([
                    'two_factor_backup_codes' => encrypt(json_encode(array_values($hashed))),
                ]);
                return true;
            }
        }

        return false;
    }

    private function generateBackupCodes(int $count = 8): array
    {
        return array_map(fn () => strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)), range(1, $count));
    }

    private function requirePragmaPackage(): void
    {
        if (!class_exists(\PragmaRX\Google2FA\Google2FA::class)) {
            throw new RuntimeException(
                'TOTP 2FA requires pragmarx/google2fa. Run: composer require pragmarx/google2fa'
            );
        }
    }
}
