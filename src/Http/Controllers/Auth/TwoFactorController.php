<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\TwoFactorService;

class TwoFactorController extends Controller
{
    public function __construct(protected TwoFactorService $tfaService) {}

    /** GET /api/auth/2fa/setup — generate secret + QR URL (not saved yet) */
    public function setup(Request $request)
    {
        $data = $this->tfaService->generateSetup($request->user());

        return response()->json([
            'message'      => 'Scan the QR code with your authenticator app, then confirm with a code.',
            'qr_url'       => $data['qr_url'],
            'secret'       => $data['secret'],
            'backup_codes' => $data['backup_codes'],
        ]);
    }

    /** POST /api/auth/2fa/confirm — validate first TOTP and persist */
    public function confirm(Request $request)
    {
        $request->validate([
            'secret'       => 'required|string',
            'totp_code'    => 'required|string|size:6',
            'backup_codes' => 'required|array|min:1',
        ]);

        $ok = $this->tfaService->confirmSetup(
            $request->user(),
            $request->secret,
            $request->totp_code,
            $request->backup_codes,
        );

        if (!$ok) {
            return response()->json(['message' => 'Invalid TOTP code. Please try again.'], 422);
        }

        return response()->json(['message' => 'Two-factor authentication enabled.']);
    }

    /** POST /api/auth/2fa/disable */
    public function disable(Request $request)
    {
        $request->validate(['totp_code' => 'required|string|size:6']);

        if (!$this->tfaService->verify($request->user(), $request->totp_code, null)) {
            return response()->json(['message' => 'Invalid TOTP code.'], 422);
        }

        $this->tfaService->disable($request->user());

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    /** POST /api/auth/2fa/backup-codes/regenerate */
    public function regenerateBackupCodes(Request $request)
    {
        $request->validate(['totp_code' => 'required|string|size:6']);

        if (!$this->tfaService->verify($request->user(), $request->totp_code, null)) {
            return response()->json(['message' => 'Invalid TOTP code.'], 422);
        }

        $codes = $this->tfaService->regenerateBackupCodes($request->user());

        return response()->json([
            'message'      => 'Backup codes regenerated. Save these — they will not be shown again.',
            'backup_codes' => $codes,
        ]);
    }
}
