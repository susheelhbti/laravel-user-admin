<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'location'   => $this->location,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
