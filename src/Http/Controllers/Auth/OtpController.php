<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Susheelhbti\LaravelUserAdmin\Http\Requests\SendOtpRequest;
use Susheelhbti\LaravelUserAdmin\Http\Requests\VerifyOtpRequest;
use Susheelhbti\LaravelUserAdmin\Http\Resources\UserResource;
use Susheelhbti\LaravelUserAdmin\Services\OtpService;

class OtpController extends Controller
{
    public function __construct(protected OtpService $otpService) {}

    public function sendOtp(SendOtpRequest $request)
    {
        $result = $this->otpService->generateAndSend($request->email);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 429);
        }

        return response()->json([
            'message' => $result['message'],
            'otp_id'  => $result['otp_id'],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $result = $this->otpService->verify(
            $request->otp_id,
            $request->code,
            $request->session()->getId()
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 401);
        }

        return response()->json([
            'message' => $result['message'],
            'user'    => new UserResource($result['user']),
            'token'   => $result['token'],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load('otpRoles', 'otpPermissions'));
    }
}
