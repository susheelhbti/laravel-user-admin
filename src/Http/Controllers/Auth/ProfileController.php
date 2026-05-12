<?php
namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\GdprService;
use Susheelhbti\LaravelUserAdmin\Services\UserLifecycleService;
use Susheelhbti\LaravelUserAdmin\Services\SecurityQuestionService;

class ProfileController extends Controller
{
    public function __construct(
        protected GdprService             $gdpr,
        protected UserLifecycleService    $lifecycle,
        protected SecurityQuestionService $sqService,
    ) {}

    /** GET /api/auth/profile/score */
    public function score(Request $request)
    {
        return response()->json($this->lifecycle->profileScore($request->user()));
    }

    /** PUT /api/auth/profile/metadata */
    public function updateMetadata(Request $request)
    {
        $request->validate(['metadata' => 'required|array']);
        $this->lifecycle->setMetadata($request->user(), $request->metadata);
        return response()->json(['message' => 'Metadata updated.']);
    }

    /** GET /api/auth/gdpr/export */
    public function gdprExport(Request $request)
    {
        $result = $this->gdpr->requestExport($request->user());
        return response()->json(['message' => 'Export ready.', ...$result]);
    }

    /** POST /api/auth/gdpr/consent */
    public function updateConsent(Request $request)
    {
        $request->validate(['consent_type' => 'required|string', 'granted' => 'required|boolean']);
        $consent = $this->gdpr->updateConsent($request->user(), $request->consent_type, $request->granted);
        return response()->json(['message' => 'Consent updated.', 'consent' => $consent]);
    }

    /** GET /api/auth/security-questions/setup */
    public function securityQuestionsBank()
    {
        return response()->json(['questions' => $this->sqService->questionBank()]);
    }

    /** POST /api/auth/security-questions/set */
    public function setSecurityQuestions(Request $request)
    {
        $required = config('user_admin.security_questions.required', 2);
        $request->validate(['answers' => "required|array|min:{$required}", 'answers.*.question' => 'required|string', 'answers.*.answer' => 'required|string']);
        $this->sqService->set($request->user(), $request->answers);
        return response()->json(['message' => 'Security questions saved.']);
    }
}
