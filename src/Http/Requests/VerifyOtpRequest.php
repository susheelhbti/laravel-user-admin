<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'otp_id' => 'required|integer|exists:otpguard_otps,id',
            'code'   => 'required|string|size:' . config('user_admin.otp.code_length', 6),
        ];
    }
}
