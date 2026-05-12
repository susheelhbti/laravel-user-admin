<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Models\Otp;
use Susheelhbti\LaravelUserAdmin\Models\Role;
use Susheelhbti\LaravelUserAdmin\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;

class OtpService
{
    public function generateAndSend(string $email): array
    {
        $cfg = config('user_admin.otp');

        // Rate limiting
        $recent = Otp::where('email', $email)
            ->where('created_at', '>', now()->subMinutes($cfg['rate_limit_minutes']))
            ->count();

        if ($recent >= $cfg['rate_limit_count']) {
            return [
                'success' => false,
                'message' => "Too many OTP requests. Please wait {$cfg['rate_limit_minutes']} minutes.",
            ];
        }

        // Invalidate old OTPs
        Otp::where('email', $email)->whereNull('verified_at')->delete();

        // Generate code
        $code = str_pad(random_int(0, (int) str_repeat('9', $cfg['code_length'])), $cfg['code_length'], '0', STR_PAD_LEFT);

        $otp = Otp::create([
            'email'      => $email,
            'code'       => bcrypt($code),
            'session_id' => Session::getId(),
            'expires_at' => now()->addMinutes($cfg['expires_in_minutes']),
            'attempts'   => 0,
        ]);

        Mail::to($email)->send(new OtpMail($code, $email));

        return [
            'success' => true,
            'message' => 'OTP sent successfully.',
            'otp_id'  => $otp->id,
        ];
    }

    public function verify(int $otpId, string $code, string $sessionId): array
    {
        $otp = Otp::find($otpId);

        if (!$otp) {
            return ['success' => false, 'message' => 'Invalid OTP request.'];
        }

        if ($otp->session_id !== $sessionId) {
            return ['success' => false, 'message' => 'Session mismatch.'];
        }

        if (!$otp->isValid()) {
            return ['success' => false, 'message' => 'OTP has expired or exceeded max attempts.'];
        }

        if (!password_verify($code, $otp->code)) {
            $otp->incrementAttempts();

            return ['success' => false, 'message' => 'Invalid OTP code.'];
        }

        $otp->markAsVerified();

        $userModel = config('user_admin.user_model', \App\Models\User::class);
        $user = $userModel::where('email', $otp->email)->first();

        if (!$user) {
            if (!config('user_admin.auto_register', true)) {
                return ['success' => false, 'message' => 'No account found for this email.'];
            }

            $user = $userModel::create([
                'name'   => explode('@', $otp->email)[0],
                'email'  => $otp->email,
                'status' => 'active',
            ]);

            $defaultRole = Role::where('slug', config('user_admin.default_role_slug', 'user'))->first();
            if ($defaultRole) {
                $user->otpRoles()->attach($defaultRole);
            }
        }

        if ($user->isSuspended()) {
            return ['success' => false, 'message' => 'Your account has been suspended.'];
        }

        $user->recordLogin(request()->ip(), request()->userAgent());

        $token = $user->createToken(config('user_admin.token_name', 'auth_token'))->plainTextToken;

        $otp->delete();

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ];
    }
}
