<?php

namespace App\Authorization;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;



class TeamMemberAuthorization
{
    public function validateContextAndAuthorization(User $user, Workspace $workspace, Team $team, string $clientId): void
    {
        // Check workspace belongs to client first
        if (!$this->workspaceBelongsToClient($workspace, $clientId)) {
            abort(404, 'Workspace not found');
        }

        // Check authorization before context validation for better error messages
        if (!$this->userHasPermissionInWorkspace($user, $workspace, 'teamMembers:create')) {
            abort(403, 'You do not have permission to create team members in this workspace');
        }

        // Check team belongs to workspace - if user is authorized but team doesn't belong, it's still a 403
        if (!$this->teamBelongsToWorkspace($team, $workspace)) {
            abort(403, 'You are not authorized to perform this action');
        }
    }

    public function validateDeleteAuthorization(User $user, Workspace $workspace, Team $team, TeamMember $teamMember, string $clientId): void
    {
        // Check workspace belongs to client first
        if (!$this->workspaceBelongsToClient($workspace, $clientId)) {
            abort(404, 'Workspace not found');
        }

        // Check team belongs to workspace - if team doesn't belong to workspace, it's unauthorized access
        if (!$this->teamBelongsToWorkspace($team, $workspace)) {
            abort(403, 'You are not authorized to perform this action');
        }

        // Check team member belongs to team
        if (!$this->teamMemberBelongsToTeam($teamMember, $team)) {
            abort(404, 'Team member not found in this team');
        }

        // Finally check authorization
        if (!$this->isWorkspaceOwner($user, $workspace) &&
            !$this->isTeamMemberSelf($user, $teamMember) &&
            !$this->userHasPermissionInWorkspace($user, $workspace, 'teamMembers:delete')) {
            abort(403, 'You are not authorized to remove this team member');
        }
    }

    public function validateInvitationAuthorization(User $user, Workspace $workspace, Team $team, TeamMember $teamMember, string $clientId): void
    {
        // Check workspace belongs to client first
        if (!$this->workspaceBelongsToClient($workspace, $clientId)) {
            abort(404, 'Workspace not found');
        }

        // Check team belongs to workspace
        if (!$this->teamBelongsToWorkspace($team, $workspace)) {
            abort(403, 'You are not authorized to perform this action');
        }

        // Check team member belongs to team
        if (!$this->teamMemberBelongsToTeam($teamMember, $team)) {
            abort(404, 'Team member not found in this team');
        }

        // Only the user who was invited can accept/reject their own invitation
        if (!$this->isTeamMemberSelf($user, $teamMember)) {
            abort(403, 'You can only manage your own team invitations');
        }
    }

    public function validateWorkspaceContext(Workspace $workspace, string $clientId): void
    {
        if (!$this->workspaceBelongsToClient($workspace, $clientId)) {
            abort(404, 'Workspace not found');
        }
    }

    public function validateTeamContext(Team $team, Workspace $workspace): void
    {
        if (!$this->teamBelongsToWorkspace($team, $workspace)) {
            abort(404, 'Team not found in this workspace');
        }
    }

    public function validateTeamMemberContext(TeamMember $teamMember, Team $team): void
    {
        if (!$this->teamMemberBelongsToTeam($teamMember, $team)) {
            abort(404, 'Team member not found in this team');
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

    private function isWorkspaceOwner(User $user, Workspace $workspace): bool
    {
        return $workspace->user_id === $user->id;
    }

    private function isTeamMemberSelf(User $user, TeamMember $teamMember): bool
    {
        return $teamMember->user_id === $user->id;
    }

    private function workspaceBelongsToClient(Workspace $workspace, string $clientId): bool
    {
        return $workspace->client_id === $clientId;
    }

    private function teamBelongsToWorkspace(Team $team, Workspace $workspace): bool
    {
        return $team->workspace_id === $workspace->id;
    }

    private function teamMemberBelongsToTeam(TeamMember $teamMember, Team $team): bool
    {
        return $teamMember->team_id === $team->id;
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
