<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\AccountDeletionService;

class AccountController extends Controller
{
    public function __construct(protected AccountDeletionService $deletionService) {}

    /** POST /api/auth/account/request-deletion */
    public function requestDeletion(Request $request)
    {
        $scheduledAt = $this->deletionService->request($request->user());

        return response()->json([
            'message'      => 'Account deletion scheduled. You will be logged out immediately.',
            'scheduled_at' => $scheduledAt->toISOString(),
            'grace_days'   => config('user_admin.account_deletion_grace_days', 7),
        ]);
    }

    /** POST /api/auth/account/cancel-deletion — user must re-authenticate first */
    public function cancelDeletion(Request $request)
    {
        $user = $request->user();

        if (!$user->deletion_requested_at) {
            return response()->json(['message' => 'No pending deletion request.'], 400);
        }

        $this->deletionService->cancel($user);

        return response()->json(['message' => 'Account deletion cancelled. Your account is safe.']);
    }
}
