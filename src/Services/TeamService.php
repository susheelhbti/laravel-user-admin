<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\Team;
use Susheelhbti\LaravelUserAdmin\Models\TeamMember;
use Susheelhbti\LaravelUserAdmin\Models\Role;

class TeamService
{
    public function create(object $owner, string $name, string $slug, array $settings = []): Team
    {
        $team = Team::create([
            'owner_id' => $owner->id,
            'name'     => $name,
            'slug'     => $slug,
            'settings' => $settings,
        ]);

        // Owner is automatically a member with owner role
        $this->addMember($team, $owner, 'owner');

        UserAdminEvents::fire(UserAdminEvents::TEAM_CREATED, [
            'team_id'  => $team->id,
            'owner_id' => $owner->id,
            'name'     => $name,
        ]);

        return $team;
    }

    public function addMember(Team $team, object $user, string $role = 'member'): TeamMember
    {
        $member = TeamMember::updateOrCreate(
            ['team_id' => $team->id, 'user_id' => $user->id],
            ['role' => $role]
        );

        UserAdminEvents::fire(UserAdminEvents::TEAM_MEMBER_ADDED, [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role'    => $role,
        ]);

        return $member;
    }

    public function removeMember(Team $team, object $user): void
    {
        TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->delete();

        UserAdminEvents::fire(UserAdminEvents::TEAM_MEMBER_REMOVED, [
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);
    }

    public function transferOwnership(Team $team, object $newOwner, object $currentOwner): void
    {
        $team->update(['owner_id' => $newOwner->id]);

        // Downgrade old owner to admin
        TeamMember::where('team_id', $team->id)->where('user_id', $currentOwner->id)
            ->update(['role' => 'admin']);

        // Promote new owner
        TeamMember::where('team_id', $team->id)->where('user_id', $newOwner->id)
            ->update(['role' => 'owner']);

        UserAdminEvents::fire(UserAdminEvents::TEAM_OWNERSHIP_TRANSFERRED, [
            'team_id'        => $team->id,
            'old_owner_id'   => $currentOwner->id,
            'new_owner_id'   => $newOwner->id,
        ]);
    }

    public function canManageUser(object $manager, Team $team, object $target): bool
    {
        $managerMember = TeamMember::where('team_id', $team->id)->where('user_id', $manager->id)->first();
        $targetMember  = TeamMember::where('team_id', $team->id)->where('user_id', $target->id)->first();

        if (!$managerMember || !$targetMember) return false;

        $hierarchy = ['owner' => 4, 'admin' => 3, 'lead' => 2, 'member' => 1];

        return ($hierarchy[$managerMember->role] ?? 0) > ($hierarchy[$targetMember->role] ?? 0);
    }

    public function delete(Team $team): void
    {
        UserAdminEvents::fire(UserAdminEvents::TEAM_DELETED, ['team_id' => $team->id, 'name' => $team->name]);
        $team->delete();
    }
}
