<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Http\Requests\SendOtpRequest;
use Susheelhbti\LaravelUserAdmin\Http\Requests\VerifyOtpRequest;
use Susheelhbti\LaravelUserAdmin\Http\Resources\UserResource;
use Susheelhbti\LaravelUserAdmin\Services\OtpService;
use Susheelhbti\LaravelUserAdmin\Services\RefreshTokenService;

class OtpController extends Controller
{
    public function __construct(
        protected OtpService          $otpService,
        protected RefreshTokenService $refreshService,
    ) {}

    public function sendOtp(SendOtpRequest $request)
    {
        $result = $this->otpService->generateAndSend($request->email);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 429);
        }

        return response()->json(['message' => $result['message'], 'otp_id' => $result['otp_id']]);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $result = $this->otpService->verify(
            $request->otp_id,
            $request->code,
            $request->session()->getId(),
            $request->totp_token,
            $request->backup_code,
        );

        // 2FA required — not yet authenticated
        if (!$result['success'] && !empty($result['requires_2fa'])) {
            return response()->json([
                'requires_2fa' => true,
                'otp_id'       => $result['otp_id'],
                'message'      => $result['message'],
            ], 200);
        }

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 401);
        }

        return response()->json([
            'message'       => $result['message'],
            'user'          => new UserResource($result['user']),
            'access_token'  => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type'    => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $this->refreshService->revokeAll($user);
        $user->tokens()->delete();

        UserAdminEvents::fire(UserAdminEvents::LOGOUT, ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load('otpRoles', 'otpPermissions'));
    }
}
