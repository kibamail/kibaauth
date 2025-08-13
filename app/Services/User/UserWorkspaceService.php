<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Collection;

class UserWorkspaceService
{
    /**
     * Get all workspaces accessible to the user for the given client.
     */
    public function getUserWorkspaces(User $user, string $clientId): Collection
    {
        $ownedWorkspaces = $this->getOwnedWorkspaces($user, $clientId);
        $memberWorkspaces = $this->getMemberWorkspaces($user, $clientId);

        return $ownedWorkspaces->merge($memberWorkspaces)->unique('id')->values();
    }

    /**
     * Get workspaces owned by the user.
     */
    protected function getOwnedWorkspaces(User $user, string $clientId): Collection
    {
        return $user->workspaces()
            ->where('client_id', $clientId)
            ->get();
    }

    /**
     * Get workspaces where the user is an active team member.
     */
    protected function getMemberWorkspaces(User $user, string $clientId): Collection
    {
        $teamMemberships = $user->teamMembers()
            ->where('status', 'active')
            ->with([
                'team.workspace' => function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                },
                'team.teamMembers.user',
                'team.permissions'
            ])
            ->get()
            ->filter(function ($teamMember) {
                return $teamMember->team && $teamMember->team->workspace;
            });

        return $teamMemberships
            ->groupBy('team.workspace.id')
            ->map(function ($memberships) {
                return $memberships->first()->team->workspace;
            })
            ->values();
    }

    /**
     * Load teams for a workspace based on user permissions.
     */
    public function loadTeamsForWorkspace($workspace, User $user): void
    {
        if ($this->userHasPermissionInWorkspace($user, $workspace, 'teams:view')) {
            $this->loadTeamsWithPermissions($workspace, $user);
        } else {
            $workspace->setRelation('teams', collect());
        }
    }

    /**
     * Load teams with appropriate member visibility based on permissions.
     */
    protected function loadTeamsWithPermissions($workspace, User $user): void
    {
        if ($this->userHasPermissionInWorkspace($user, $workspace, 'teamMembers:view')) {
            $allTeams = Team::where('workspace_id', $workspace->id)
                ->with(['teamMembers' => function ($query) {
                    $query->where('status', 'active')->with('user');
                }, 'permissions'])
                ->get();
        } else {
            $allTeams = Team::where('workspace_id', $workspace->id)
                ->with(['permissions'])
                ->get();

            foreach ($allTeams as $team) {
                $team->setRelation('teamMembers', collect());
            }
        }

        $workspace->setRelation('teams', $allTeams);
    }

    /**
     * Check if user has specific permission in workspace through team membership.
     */
    public function userHasPermissionInWorkspace(User $user, $workspace, string $permissionSlug): bool
    {
        if ($user->id === $workspace->user_id) {
            return true;
        }

        $userTeams = $workspace->teams()
            ->whereHas('teamMembers', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', 'active');
            })
            ->with('permissions')
            ->get();

        foreach ($userTeams as $team) {
            if ($team->permissions->contains('slug', $permissionSlug)) {
                return true;
            }
        }

        return false;
    }
}
