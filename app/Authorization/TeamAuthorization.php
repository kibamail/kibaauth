<?php

namespace App\Authorization;

use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class TeamAuthorization
{
    public function validateWorkspaceContext(Workspace $workspace, string $clientId): void
    {
        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }
    }

    public function validateTeamContext(Team $team, Workspace $workspace): void
    {
        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }
    }

    public function getClientId(Request $request): string
    {
        $token = $request->user()->token();
        $clientId = $token->client_id ?? $token->client->id ?? null;

        if (!$clientId) {
            abort(400, 'Client context not available');
        }

        return $clientId;
    }

    /**
     * Check if user has specific permission in workspace through team membership
     */
    public function userHasPermissionInWorkspace(User $user, Workspace $workspace, string $permissionSlug): bool
    {
        // Check if user is workspace owner (always has all permissions)
        if ($user->id === $workspace->user_id) {
            return true;
        }

        // Check if user is a team member in any team that has the required permission
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
