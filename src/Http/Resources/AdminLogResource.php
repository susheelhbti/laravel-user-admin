<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'admin'      => [
                'id'    => $this->admin->id,
                'name'  => $this->admin->name,
                'email' => $this->admin->email,
            ],
            'target_user' => $this->whenLoaded('targetUser', fn () => $this->targetUser ? [
                'id'    => $this->targetUser->id,
                'name'  => $this->targetUser->name,
                'email' => $this->targetUser->email,
            ] : null),
            'action'     => $this->action,
            'details'    => $this->details,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
