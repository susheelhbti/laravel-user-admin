<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\DeviceService;

class DeviceController extends Controller
{
    public function __construct(protected DeviceService $deviceService) {}

    /** GET /api/auth/devices */
    public function index(Request $request)
    {
        $devices = $this->deviceService->listDevices($request->user());

        return response()->json(['devices' => $devices]);
    }

    /** POST /api/auth/devices/trust */
    public function trust(Request $request)
    {
        $request->validate(['device_name' => 'nullable|string|max:100']);

        $data = $this->deviceService->trustDevice($request->user(), $request, $request->device_name ?? '');

        return response()->json([
            'message'      => 'Device trusted. 2FA will be skipped on this device.',
            'device_id'    => $data['device_id'],
            'device_token' => $data['device_token'],
        ])->cookie('trusted_device', $data['device_token'], 60 * 24 * 90);   // 90 days
    }

    /** DELETE /api/auth/devices/{device} */
    public function revoke(Request $request, int $deviceId)
    {
        $ok = $this->deviceService->revokeDevice($request->user(), $deviceId);

        if (!$ok) {
            return response()->json(['message' => 'Device not found.'], 404);
        }

        return response()->json(['message' => 'Device revoked.']);
    }

    /** DELETE /api/auth/devices */
    public function revokeAll(Request $request)
    {
        $this->deviceService->revokeAll($request->user());

        return response()->json(['message' => 'All trusted devices revoked.']);
    }
}
