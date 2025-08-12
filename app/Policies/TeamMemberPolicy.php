<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;

class TeamMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TeamMember $teamMember): bool
    {
        $workspace = $teamMember->team->workspace;

        return $this->isWorkspaceOwner($user, $workspace) ||
               $this->isTeamMemberSelf($user, $teamMember);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->isWorkspaceOwner($user, $team->workspace);
    }

    public function update(User $user, TeamMember $teamMember): bool
    {
        $workspace = $teamMember->team->workspace;

        return $this->isWorkspaceOwner($user, $workspace);
    }

    public function delete(User $user, TeamMember $teamMember): bool
    {
        $workspace = $teamMember->team->workspace;

        return $this->isWorkspaceOwner($user, $workspace) ||
               $this->isTeamMemberSelf($user, $teamMember);
    }

    public function restore(User $user, TeamMember $teamMember): bool
    {
        $workspace = $teamMember->team->workspace;

        return $this->isWorkspaceOwner($user, $workspace);
    }

    public function forceDelete(User $user, TeamMember $teamMember): bool
    {
        $workspace = $teamMember->team->workspace;

        return $this->isWorkspaceOwner($user, $workspace);
    }

    private function isWorkspaceOwner(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->user_id;
    }

    private function isTeamMemberSelf(User $user, TeamMember $teamMember): bool
    {
        return $teamMember->user_id === $user->id;
    }
}
