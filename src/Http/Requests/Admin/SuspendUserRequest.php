<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SuspendUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOtpAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'suspended_until' => 'nullable|date|after:now',
            'reason'          => 'nullable|string|max:500',
            'notify'          => 'nullable|boolean',
        ];
    }
}
