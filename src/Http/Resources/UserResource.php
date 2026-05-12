<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'status'                => $this->status,
            'roles'                 => $this->whenLoaded('otpRoles', fn () => $this->otpRoles->pluck('slug')),
            'permissions'           => $this->whenLoaded('otpPermissions', fn () => $this->otpPermissions->pluck('slug')),
            'two_factor_enabled'    => $this->two_factor_enabled,
            'last_login_at'         => $this->last_login_at?->toISOString(),
            'last_login_ip'         => $this->last_login_ip,
            'suspended_until'       => $this->suspended_until?->toISOString(),
            // v3 fields
            'tags'                  => $this->tags ?? [],
            'metadata'              => $this->metadata ?? [],
            'profile_score'         => $this->profile_score ?? 0,
            'account_expires_at'    => $this->account_expires_at?->toISOString(),
            'deletion_requested_at' => $this->deletion_requested_at?->toISOString(),
            'deletion_scheduled_at' => $this->deletion_scheduled_at?->toISOString(),
            'failed_login_count'    => $this->failed_login_count ?? 0,
            'created_at'            => $this->created_at->toISOString(),
            'updated_at'            => $this->updated_at->toISOString(),
        ];
    }
}
