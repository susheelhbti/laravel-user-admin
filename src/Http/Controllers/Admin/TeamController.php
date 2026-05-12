<?php
namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Models\Team;
use Susheelhbti\LaravelUserAdmin\Services\TeamService;

class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    public function index(Request $request)
    {
        $q = Team::with('owner')->withCount('members');
        if ($request->filled('search')) $q->where('name', 'like', '%'.$request->search.'%');
        return response()->json($q->paginate($request->get('per_page', 15)));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'slug' => 'required|string|unique:user_admin_teams,slug']);
        $team = $this->teamService->create($request->user(), $request->name, $request->slug, $request->get('settings', []));
        return response()->json(['message' => 'Team created.', 'team' => $team], 201);
    }

    public function show(Team $team)
    {
        return response()->json($team->load(['owner', 'members.user']));
    }

    public function update(Request $request, Team $team)
    {
        $request->validate(['name' => 'sometimes|string|max:255', 'settings' => 'nullable|array']);
        $team->update($request->only(['name', 'settings']));
        return response()->json(['message' => 'Team updated.', 'team' => $team]);
    }

    public function destroy(Team $team)
    {
        $this->teamService->delete($team);
        return response()->json(['message' => 'Team deleted.']);
    }

    public function addMember(Request $request, Team $team)
    {
        $request->validate(['user_id' => 'required|exists:users,id', 'role' => 'nullable|in:owner,admin,lead,member']);
        $model = config('user_admin.user_model', \App\Models\User::class);
        $user  = $model::findOrFail($request->user_id);
        $member = $this->teamService->addMember($team, $user, $request->get('role', 'member'));
        return response()->json(['message' => 'Member added.', 'member' => $member]);
    }

    public function removeMember(Request $request, Team $team)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $model = config('user_admin.user_model', \App\Models\User::class);
        $user  = $model::findOrFail($request->user_id);
        $this->teamService->removeMember($team, $user);
        return response()->json(['message' => 'Member removed.']);
    }

    public function transferOwnership(Request $request, Team $team)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $model   = config('user_admin.user_model', \App\Models\User::class);
        $newOwner = $model::findOrFail($request->user_id);
        $this->teamService->transferOwnership($team, $newOwner, $request->user());
        return response()->json(['message' => 'Ownership transferred.']);
    }
}
