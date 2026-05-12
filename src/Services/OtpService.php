<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\Otp;
use Susheelhbti\LaravelUserAdmin\Models\Role;
use Susheelhbti\LaravelUserAdmin\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class OtpService
{
    public function __construct(
        protected TwoFactorService       $tfaService,
        protected RefreshTokenService    $refreshService,
        protected DeviceService          $deviceService,
        protected GeoService             $geoService,
        protected ConditionalAuthService $rulesService,
    ) {}

    // ── Send ──────────────────────────────────────────────────────────────────

    public function generateAndSend(string $email): array
    {
        $cfg = config('user_admin.otp');

        $recent = Otp::where('email', $email)
            ->where('created_at', '>', now()->subMinutes($cfg['rate_limit_minutes']))
            ->count();

        if ($recent >= $cfg['rate_limit_count']) {
            UserAdminEvents::fire(UserAdminEvents::OTP_FAILED, ['email' => $email, 'reason' => 'rate_limited']);
            return ['success' => false, 'message' => "Too many OTP requests. Please wait {$cfg['rate_limit_minutes']} minutes."];
        }

        Otp::where('email', $email)->whereNull('verified_at')->delete();

        $code = str_pad(random_int(0, (int) str_repeat('9', $cfg['code_length'])), $cfg['code_length'], '0', STR_PAD_LEFT);

        $otp = Otp::create([
            'email'      => $email,
            'code'       => bcrypt($code),
            'session_id' => Session::getId(),
            'expires_at' => now()->addMinutes($cfg['expires_in_minutes']),
            'attempts'   => 0,
        ]);

        try {
            Mail::to($email)->send(new OtpMail($code, $email));
            UserAdminEvents::fire(UserAdminEvents::EMAIL_SENT, ['type' => 'otp', 'email' => $email]);
        } catch (\Throwable $e) {
            UserAdminEvents::fire(UserAdminEvents::EMAIL_FAILED, ['error' => $e->getMessage(), 'email' => $email]);
            return ['success' => false, 'message' => 'Failed to send OTP email.'];
        }

        UserAdminEvents::fire(UserAdminEvents::OTP_SENT, ['email' => $email]);
        return ['success' => true, 'message' => 'OTP sent successfully.', 'otp_id' => $otp->id];
    }

    // ── Verify ────────────────────────────────────────────────────────────────

    public function verify(int $otpId, string $code, string $sessionId, ?string $totpToken = null, ?string $backupCode = null): array
    {
        $otp = Otp::find($otpId);
        if (!$otp) return ['success' => false, 'message' => 'Invalid OTP request.'];
        if ($otp->session_id !== $sessionId) return ['success' => false, 'message' => 'Session mismatch.'];

        if (!$otp->isValid()) {
            UserAdminEvents::fire(UserAdminEvents::OTP_FAILED, ['email' => $otp->email, 'reason' => 'expired_or_max_attempts']);
            return ['success' => false, 'message' => 'OTP has expired or exceeded max attempts.'];
        }

        if (!password_verify($code, $otp->code)) {
            $otp->incrementAttempts();
            UserAdminEvents::fire(UserAdminEvents::OTP_FAILED, ['email' => $otp->email, 'reason' => 'wrong_code']);
            return ['success' => false, 'message' => 'Invalid OTP code.'];
        }

        $otp->markAsVerified();
        UserAdminEvents::fire(UserAdminEvents::OTP_VERIFIED, ['email' => $otp->email]);

        // ── Find or auto-register user ─────────────────────────────────────────
        $userModel = config('user_admin.user_model', \App\Models\User::class);
        $user      = $userModel::where('email', $otp->email)->first();

        if (!$user) {
            if (!config('user_admin.auto_register', true)) {
                UserAdminEvents::fire(UserAdminEvents::LOGIN_FAILED, ['email' => $otp->email, 'reason' => 'no_account']);
                return ['success' => false, 'message' => 'No account found for this email.'];
            }

            $user = $userModel::create(['name' => explode('@', $otp->email)[0], 'email' => $otp->email, 'status' => 'active']);
            $defaultRole = Role::where('slug', config('user_admin.default_role_slug', 'user'))->first();
            if ($defaultRole) $user->otpRoles()->attach($defaultRole);
            UserAdminEvents::fire(UserAdminEvents::USER_CREATED, ['user_id' => $user->id, 'email' => $user->email, 'source' => 'otp_auto_register']);
        }

        if ($user->isSuspended()) {
            UserAdminEvents::fire(UserAdminEvents::LOGIN_FAILED, ['email' => $user->email, 'reason' => 'suspended']);
            return ['success' => false, 'message' => 'Your account has been suspended.'];
        }

        // ── Geolocation check ──────────────────────────────────────────────────
        $ip  = request()->ip();
        $geo = $this->geoService->checkLogin($ip, $user->id, $user->last_login_ip, $user->last_login_at);
        if (!$geo['allowed']) {
            return ['success' => false, 'message' => match($geo['reason']) {
                'geo_blocked'       => 'Login blocked: your country is not allowed.',
                'ip_reputation'     => 'Login blocked: your IP has been flagged.',
                'impossible_travel' => 'Login blocked: suspicious location change detected.',
                default             => 'Login blocked by security policy.',
            }];
        }

        // ── Conditional auth rules ─────────────────────────────────────────────
        $context = ['user' => $user, 'ip' => $ip, 'geo' => $geo['geo'], 'failed_login_count' => $user->failed_login_count ?? 0];
        $rule = $this->rulesService->evaluate($context);
        if (in_array($rule['action'], ['block', 'suspend'])) {
            UserAdminEvents::fire(UserAdminEvents::LOGIN_FAILED, ['email' => $user->email, 'reason' => 'auth_rule_'.$rule['action']]);
            return ['success' => false, 'message' => 'Login blocked by security policy.'];
        }
        if ($rule['action'] === 'require_captcha') {
            return ['success' => false, 'requires_captcha' => true, 'message' => 'Please complete the CAPTCHA.'];
        }

        // ── 2FA ────────────────────────────────────────────────────────────────
        $isKnownDevice = $this->deviceService->isTrusted($user, request());

        // Bug #9 fix — fire LOGIN_NEW_DEVICE on first login from an unknown device
        if (!$isKnownDevice) {
            UserAdminEvents::fire(UserAdminEvents::LOGIN_NEW_DEVICE, [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'ip'         => $ip,
                'user_agent' => request()->userAgent(),
            ]);
        }

        if ($user->two_factor_enabled && !$isKnownDevice) {
            if (!$totpToken && !$backupCode) {
                return ['success' => false, 'requires_2fa' => true, 'otp_id' => $otp->id, 'message' => 'Two-factor authentication required.'];
            }
            if (!$this->tfaService->verify($user, $totpToken, $backupCode)) {
                $user->increment('failed_login_count');
                UserAdminEvents::fire(UserAdminEvents::TFA_FAILED, ['user_id' => $user->id, 'ip' => $ip]);
                return ['success' => false, 'message' => 'Invalid two-factor code.'];
            }
        }

        // ── Login approval (invite-only mode) ─────────────────────────────────
        if (config('user_admin.require_login_approval', false) && !($user->login_approved ?? true)) {
            UserAdminEvents::fire(UserAdminEvents::LOGIN_APPROVAL_REQUIRED, [
                'user_id' => $user->id,
                'email'   => $user->email,
                'ip'      => $ip,
            ]);
            return ['success' => false, 'message' => 'Your login requires admin approval.'];
        }

        // ── Issue tokens ───────────────────────────────────────────────────────
        $user->update(['failed_login_count' => 0, 'last_failed_login_at' => null]);
        $user->recordLogin($ip, request()->userAgent());

        $sanctumToken = $user->createToken(config('user_admin.token_name', 'auth_token'));
        $accessToken  = $sanctumToken->plainTextToken;
        $refreshToken = $this->refreshService->issue($user, $sanctumToken->accessToken->id);

        $otp->delete();

        UserAdminEvents::fire(UserAdminEvents::LOGIN_SUCCESS, ['user_id' => $user->id, 'email' => $user->email, 'ip' => $ip, 'geo' => $geo['geo']]);

        return [
            'success'       => true,
            'message'       => 'Login successful.',
            'user'          => $user,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'token'         => $accessToken,
        ];
    }
}
