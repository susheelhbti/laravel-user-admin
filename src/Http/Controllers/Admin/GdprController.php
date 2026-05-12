<?php
namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\GdprService;

class GdprController extends Controller
{
    public function __construct(protected GdprService $gdprService) {}

    /** POST /api/admin/users/{user}/gdpr/export */
    public function export($userId)
    {
        $model = config('user_admin.user_model', \App\Models\User::class);
        $user  = $model::findOrFail($userId);
        $result = $this->gdprService->requestExport($user);
        return response()->json(['message' => 'Export ready.', ...$result]);
    }

    /** POST /api/admin/users/{user}/gdpr/anonymise */
    public function anonymise($userId)
    {
        $model = config('user_admin.user_model', \App\Models\User::class);
        $user  = $model::findOrFail($userId);
        $this->gdprService->anonymise($user);
        return response()->json(['message' => 'User data anonymised (right to erasure applied).']);
    }
}
