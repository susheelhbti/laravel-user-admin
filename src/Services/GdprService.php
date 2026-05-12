<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\GdprConsent;
use Illuminate\Support\Facades\Storage;

class GdprService
{
    /**
     * Export all personal data for a user as a JSON file.
     * Fires GDPR_EXPORT_REQUESTED immediately, GDPR_EXPORT_READY when file is ready.
     */
    public function requestExport(object $user): array
    {
        UserAdminEvents::fire(UserAdminEvents::GDPR_EXPORT_REQUESTED, [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);

        $data = $this->collectUserData($user);

        $filename = "gdpr_export_{$user->id}_" . now()->format('Y-m-d_His') . '.json';
        $path     = 'gdpr_exports/' . $filename;

        Storage::put($path, json_encode($data, JSON_PRETTY_PRINT));

        // Signed URL valid for 24h
        $url = Storage::temporaryUrl($path, now()->addDay());

        UserAdminEvents::fire(UserAdminEvents::GDPR_EXPORT_READY, [
            'user_id'  => $user->id,
            'email'    => $user->email,
            'filename' => $filename,
        ]);

        return ['download_url' => $url, 'expires_in' => '24 hours', 'filename' => $filename];
    }

    /**
     * Collect all personal data associated with a user.
     */
    public function collectUserData(object $user): array
    {
        $user->loadMissing(['otpRoles', 'loginHistories', 'trustedDevices', 'adminLogs']);

        return [
            'exported_at' => now()->toISOString(),
            'profile'     => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'status'     => $user->status,
                'created_at' => $user->created_at,
            ],
            'roles'         => $user->otpRoles->pluck('slug'),
            'metadata'      => $user->metadata ?? [],
            'login_history' => $user->loginHistories->map(fn ($l) => [
                'ip'         => $l->ip_address,
                'user_agent' => $l->user_agent,
                'location'   => $l->location,
                'at'         => $l->created_at,
            ]),
            'trusted_devices' => $user->trustedDevices->map(fn ($d) => [
                'device_name'  => $d->device_name,
                'ip'           => $d->ip_address,
                'last_used_at' => $d->last_used_at,
            ]),
            'consents' => GdprConsent::where('user_id', $user->id)->get()->map(fn ($c) => [
                'type'       => $c->consent_type,
                'granted'    => $c->granted,
                'updated_at' => $c->updated_at,
            ]),
        ];
    }

    /**
     * Update a user's consent for a specific processing type.
     */
    public function updateConsent(object $user, string $consentType, bool $granted): GdprConsent
    {
        $consent = GdprConsent::updateOrCreate(
            ['user_id' => $user->id, 'consent_type' => $consentType],
            ['granted' => $granted, 'ip_address' => request()->ip()]
        );

        UserAdminEvents::fire(UserAdminEvents::GDPR_CONSENT_UPDATED, [
            'user_id'      => $user->id,
            'consent_type' => $consentType,
            'granted'      => $granted,
        ]);

        return $consent;
    }

    /**
     * Anonymise a user's personal data in place (right to erasure).
     * Keeps the user row for referential integrity but removes PII.
     */
    public function anonymise(object $user): void
    {
        $anonymousId = 'deleted_' . $user->id;

        $user->update([
            'name'               => 'Deleted User',
            'email'              => $anonymousId . '@deleted.invalid',
            'status'             => 'deleted',
            'two_factor_secret'  => null,
            'two_factor_backup_codes' => null,
            'metadata'           => null,
            'last_login_ip'      => null,
        ]);

        $user->tokens()->delete();
        $user->trustedDevices()->delete();
        $user->loginHistories()->delete();

        UserAdminEvents::fire(UserAdminEvents::GDPR_ERASURE_REQUESTED, [
            'user_id'        => $user->id,
            'anonymised'     => true,
        ]);
    }
}
