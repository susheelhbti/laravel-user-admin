<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Susheelhbti\LaravelUserAdmin\Http\Requests\Admin\SuspendUserRequest;
use Susheelhbti\LaravelUserAdmin\Http\Requests\Admin\UpdateUserRequest;
use Susheelhbti\LaravelUserAdmin\Http\Resources\AdminLogResource;
use Susheelhbti\LaravelUserAdmin\Http\Resources\LoginHistoryResource;
use Susheelhbti\LaravelUserAdmin\Http\Resources\UserResource;
use Susheelhbti\LaravelUserAdmin\Mail\UserSuspendedMail;
use Susheelhbti\LaravelUserAdmin\Models\AdminLog;
use Susheelhbti\LaravelUserAdmin\Models\Role;

class UserManagementController extends Controller
{
    protected function userModel(): string
    {
        return config('user_admin.user_model', \App\Models\User::class);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $model = $this->userModel();
        $query = $model::query()->with('otpRoles');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $query->whereHas('otpRoles', fn ($q) => $q->where('slug', $request->role));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }

        $sortField = $request->get('sort_by', 'created_at');
        $sortDir   = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDir);

        $users = $query->paginate($request->get('per_page', config('user_admin.per_page', 15)));

        $this->log(auth()->id(), null, 'view_users', ['filters' => $request->all()]);

        return UserResource::collection($users);
    }

    public function show($userId)
    {
        $user = $this->findUser($userId);

        $this->log(auth()->id(), $user->id, 'view_user_details');

        return new UserResource($user->load('otpRoles', 'otpPermissions'));
    }

    public function store(UpdateUserRequest $request)
    {
        $model = $this->userModel();

        $user = $model::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'status'   => $request->get('status', 'active'),
            'password' => $request->filled('password') ? Hash::make($request->password) : null,
        ]);

        if ($request->filled('role')) {
            $role = Role::where('slug', $request->role)->first();
            if ($role) {
                $user->otpRoles()->attach($role);
            }
        }

        $this->log(auth()->id(), $user->id, 'create_user', $request->only(['name', 'email', 'role']));

        UserAdminEvents::fire(UserAdminEvents::USER_CREATED, [
            'user_id' => $user->id,
            'email'   => $user->email,
            'source'  => 'admin',
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user'    => new UserResource($user->load('otpRoles')),
        ], 201);
    }

    public function update(UpdateUserRequest $request, $userId)
    {
        $user    = $this->findUser($userId);
        $oldData = $user->only(['name', 'email', 'status']);

        $user->update($request->only(['name', 'email', 'status']));

        if ($request->filled('role')) {
            $role = Role::where('slug', $request->role)->first();
            if ($role) {
                $user->otpRoles()->sync([$role->id]);
            }
        }

        $this->log(auth()->id(), $user->id, 'update_user', ['old' => $oldData, 'new' => $request->only(['name', 'email', 'status', 'role'])]);

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => new UserResource($user->fresh('otpRoles')),
        ]);
    }

    public function destroy($userId)
    {
        $user      = $this->findUser($userId);
        $userEmail = $user->email;

        $this->log(auth()->id(), $user->id, 'delete_user', ['email' => $userEmail]);
        $user->forceDelete();

        return response()->json(['message' => 'User permanently deleted.']);
    }

    public function softDelete($userId)
    {
        $user = $this->findUser($userId);

        $this->log(auth()->id(), $user->id, 'soft_delete_user', ['email' => $user->email]);
        $user->delete();

        return response()->json(['message' => 'User moved to trash.']);
    }

    // ── Status actions ────────────────────────────────────────────────────────

    public function suspend(SuspendUserRequest $request, $userId)
    {
        $user = $this->findUser($userId);

        $user->update(['status' => 'suspended', 'suspended_until' => $request->suspended_until]);
        $user->tokens()->delete();

        $this->log(auth()->id(), $user->id, 'suspend_user', [
            'suspended_until' => $request->suspended_until,
            'reason'          => $request->reason,
        ]);

        if ($request->boolean('notify')) {
            Mail::to($user->email)->send(new UserSuspendedMail($user, $request->reason));
        }

        return response()->json([
            'message'         => 'User suspended successfully.',
            'suspended_until' => $request->suspended_until,
        ]);
    }

    public function unsuspend($userId)
    {
        $user        = $this->findUser($userId);
        $wasArchived = $user->status === 'archived';

        $user->update(['status' => 'active', 'suspended_until' => null]);

        $this->log(auth()->id(), $user->id, 'unsuspend_user');

        // Bug #9 fix — fire USER_UNARCHIVED when restoring an archived user
        if ($wasArchived) {
            UserAdminEvents::fire(UserAdminEvents::USER_UNARCHIVED, [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);
        } else {
            UserAdminEvents::fire(UserAdminEvents::USER_UNSUSPENDED, [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);
        }

        return response()->json(['message' => 'User unsuspended successfully.']);
    }

    public function temporaryBan(SuspendUserRequest $request, $userId)
    {
        if (!$request->filled('suspended_until')) {
            return response()->json(['message' => 'suspended_until is required for a temporary ban.'], 422);
        }

        $user = $this->findUser($userId);
        $user->update(['status' => 'suspended', 'suspended_until' => $request->suspended_until]);
        $user->tokens()->delete();

        $this->log(auth()->id(), $user->id, 'temporary_ban', [
            'suspended_until' => $request->suspended_until,
            'reason'          => $request->reason,
        ]);

        return response()->json([
            'message'    => 'User temporarily banned.',
            'banned_until' => $request->suspended_until,
        ]);
    }

    // ── Security actions ──────────────────────────────────────────────────────

    public function forcePasswordReset($userId)
    {
        $user       = $this->findUser($userId);
        $resetToken = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($resetToken), 'created_at' => now()]
        );

        $this->log(auth()->id(), $user->id, 'force_password_reset');

        return response()->json([
            'message'     => 'Password reset forced.',
            'reset_token' => $resetToken,
        ]);
    }

    public function removeTwoFactor($userId)
    {
        $user = $this->findUser($userId);
        $user->update(['two_factor_enabled' => false, 'two_factor_secret' => null]);

        $this->log(auth()->id(), $user->id, 'remove_2fa');

        return response()->json(['message' => 'Two-factor authentication removed.']);
    }

    public function terminateAllSessions($userId)
    {
        $user = $this->findUser($userId);
        $user->tokens()->delete();

        $this->log(auth()->id(), $user->id, 'terminate_sessions');

        return response()->json(['message' => 'All sessions terminated.']);
    }

    // ── History & Impersonation ───────────────────────────────────────────────

    public function loginHistory($userId)
    {
        $user = $this->findUser($userId);

        $this->log(auth()->id(), $user->id, 'view_login_history');

        return LoginHistoryResource::collection($user->loginHistories()->latest()->paginate(20));
    }

    public function impersonate($userId)
    {
        $user = $this->findUser($userId);

        session(['otpguard_impersonate_admin_id' => auth()->id()]);

        $token = $user->createToken('impersonation_token')->plainTextToken;

        $this->log(auth()->id(), $user->id, 'impersonate_user');

        return response()->json([
            'message'       => 'Now impersonating user.',
            'user'          => new UserResource($user),
            'token'         => $token,
            'impersonating' => true,
        ]);
    }

    public function stopImpersonation(Request $request)
    {
        $adminId = session('otpguard_impersonate_admin_id');

        if (!$adminId) {
            return response()->json(['message' => 'Not currently impersonating.'], 400);
        }

        $model = $this->userModel();
        $admin = $model::findOrFail($adminId);
        $token = $admin->createToken(config('user_admin.token_name', 'auth_token'))->plainTextToken;

        session()->forget('otpguard_impersonate_admin_id');

        return response()->json(['message' => 'Stopped impersonation.', 'token' => $token]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $model  = $this->userModel();
        $query  = $model::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $users       = $query->get(['id', 'name', 'email', 'status', 'created_at', 'last_login_at']);
        $csvFileName = 'users_export_' . now()->format('Y-m-d_His') . '.csv';
        $exportDir   = storage_path('app/exports');

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $csvPath = $exportDir . '/' . $csvFileName;
        $file    = fopen($csvPath, 'w');
        fputcsv($file, ['ID', 'Name', 'Email', 'Status', 'Created At', 'Last Login']);

        foreach ($users as $user) {
            fputcsv($file, [$user->id, $user->name, $user->email, $user->status, $user->created_at, $user->last_login_at]);
        }

        fclose($file);

        $this->log(auth()->id(), null, 'export_users', $request->all());

        return response()->download($csvPath)->deleteFileAfterSend(true);
    }

    // ── Bulk operations ───────────────────────────────────────────────────────

    public function bulkSuspend(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'reason'     => 'nullable|string',
        ]);

        $model = $this->userModel();
        $count = 0;

        foreach ($request->user_ids as $id) {
            $user = $model::find($id);
            if ($user && $user->status !== 'suspended') {
                $user->update(['status' => 'suspended']);
                $user->tokens()->delete();
                $count++;
            }
        }

        $this->log(auth()->id(), null, 'bulk_suspend', ['ids' => $request->user_ids, 'count' => $count]);

        return response()->json(['message' => "{$count} users suspended successfully."]);
    }

    public function bulkUnsuspend(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $model = $this->userModel();
        $count = $model::whereIn('id', $request->user_ids)
            ->where('status', 'suspended')
            ->update(['status' => 'active', 'suspended_until' => null]);

        $this->log(auth()->id(), null, 'bulk_unsuspend', ['ids' => $request->user_ids, 'count' => $count]);

        return response()->json(['message' => "{$count} users unsuspended successfully."]);
    }

    public function bulkAssignRole(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role'       => 'required|exists:otpguard_roles,slug',
        ]);

        $model = $this->userModel();
        $role  = Role::where('slug', $request->role)->first();
        $count = 0;

        foreach ($request->user_ids as $id) {
            $user = $model::find($id);
            if ($user && !$user->hasOtpRole($request->role)) {
                $user->otpRoles()->attach($role);
                $count++;
            }
        }

        $this->log(auth()->id(), null, 'bulk_assign_role', ['ids' => $request->user_ids, 'role' => $request->role, 'count' => $count]);

        return response()->json(['message' => "{$count} users assigned role '{$request->role}'."]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $model = $this->userModel();
        $count = $model::whereIn('id', $request->user_ids)->forceDelete();

        $this->log(auth()->id(), null, 'bulk_delete', ['ids' => $request->user_ids, 'count' => $count]);

        return response()->json(['message' => "{$count} users permanently deleted."]);
    }

    // ── Stats & Logs ──────────────────────────────────────────────────────────

    public function statistics()
    {
        $model = $this->userModel();

        $stats = [
            'total_users'        => $model::count(),
            'active_users'       => $model::where('status', 'active')->count(),
            'suspended_users'    => $model::where('status', 'suspended')->count(),
            'new_users_today'    => $model::whereDate('created_at', today())->count(),
            'users_last_7_days'  => $model::where('created_at', '>=', now()->subDays(7))->count(),
            'users_by_role'      => [],
        ];

        Role::withCount('users')->get()->each(function ($role) use (&$stats) {
            $stats['users_by_role'][$role->slug] = $role->users_count;
        });

        $this->log(auth()->id(), null, 'view_statistics');

        return response()->json($stats);
    }

    public function adminLogs(Request $request)
    {
        $query = AdminLog::with(['admin', 'targetUser']);

        if ($request->filled('user_id')) {
            $query->where('target_user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 30));

        return AdminLogResource::collection($logs);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function findUser($id)
    {
        $model = $this->userModel();

        return $model::findOrFail($id);
    }

    protected function log($adminId, $targetUserId, string $action, array $details = []): void
    {
        AdminLog::create([
            'admin_id'       => $adminId,
            'target_user_id' => $targetUserId,
            'action'         => $action,
            'details'        => $details,
            'ip_address'     => request()->ip(),
            'request_id'     => request()->header('X-Request-ID', uniqid('req_', true)),
        ]);
    }
}
