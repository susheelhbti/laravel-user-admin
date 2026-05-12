<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOtpAdmin() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'status'   => 'sometimes|in:active,suspended',
            'role'     => 'sometimes|exists:otpguard_roles,slug',
            'password' => 'sometimes|string|min:8',
        ];
    }
}
