<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display the authenticated user with all their workspaces, teams, and related data.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $clientId = $this->getClientId($request);

        // Get workspaces owned by the user for this client
        $ownedWorkspaces = $user->workspaces()
            ->where('client_id', $clientId)
            ->get();

        // Get workspaces where the user is a team member for this client
        $memberWorkspaces = collect();

        // Find all active team memberships for this user (exclude pending invitations)
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

        // Get pending invitations for this user
        $pendingInvitations = $this->getPendingInvitations($user, $clientId);

        // Group team memberships by workspace
        $memberWorkspaceGroups = $teamMemberships->groupBy('team.workspace.id');

        foreach ($memberWorkspaceGroups as $workspaceId => $memberships) {
            $workspace = $memberships->first()->team->workspace;

            // Load teams conditionally based on permissions
            $this->loadTeamsConditionally($workspace, $user);

            $memberWorkspaces->push($workspace);
        }

        // Load teams conditionally for owned workspaces
        foreach ($ownedWorkspaces as $workspace) {
            $this->loadTeamsConditionally($workspace, $user);
        }

        // Merge owned and member workspaces, removing duplicates
        $allWorkspaces = $ownedWorkspaces->merge($memberWorkspaces)->unique('id')->values();

        // Load the user data without sensitive information
        $userData = $user->only(['id', 'email', 'email_verified_at', 'created_at', 'updated_at']);

        return response()->json([
            'data' => array_merge($userData, [
                'workspaces' => $allWorkspaces,
                'pending_invitations' => $pendingInvitations
            ])
        ]);
    }

    /**
     * Get the client ID from the request token.
     */
    protected function getClientId(Request $request): string
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
    protected function userHasPermissionInWorkspace(User $user, $workspace, string $permissionSlug): bool
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

    /**
     * Load teams conditionally based on user permissions
     */
    protected function loadTeamsConditionally($workspace, User $user): void
    {
        // Check if user has teams:view permission
        if ($this->userHasPermissionInWorkspace($user, $workspace, 'teams:view')) {
            // User can see teams - get ALL teams in workspace with fresh query
            if ($this->userHasPermissionInWorkspace($user, $workspace, 'teamMembers:view')) {
                // User can see both teams and team members (only active members)
                $allTeams = Team::where('workspace_id', $workspace->id)
                    ->with(['teamMembers' => function ($query) {
                        $query->where('status', 'active')->with('user');
                    }, 'permissions'])
                    ->get();
            } else {
                // User can see teams but not team members
                $allTeams = Team::where('workspace_id', $workspace->id)
                    ->with(['permissions'])
                    ->get();
                // Set empty team_members array for each team
                foreach ($allTeams as $team) {
                    $team->setRelation('teamMembers', collect());
                }
            }
            $workspace->setRelation('teams', $allTeams);
        } else {
            // User cannot see teams - set empty teams array
            $workspace->setRelation('teams', collect());
        }
    }

    /**
     * Get pending invitations for the user
     */
    protected function getPendingInvitations(User $user, string $clientId): array
    {
        $pendingInvitations = $user->teamMembers()
            ->where('status', 'pending')
            ->with([
                'team.workspace' => function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                },
                'team'
            ])
            ->get()
            ->filter(function ($teamMember) {
                return $teamMember->team && $teamMember->team->workspace;
            })
            ->map(function ($teamMember) {
                $workspace = $teamMember->team->workspace;
                return [
                    'invitation_id' => $teamMember->id,
                    'team_id' => $teamMember->team->id,
                    'team_name' => $teamMember->team->name,
                    'workspace' => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'slug' => $workspace->slug,
                        'created_at' => $workspace->created_at,
                        'updated_at' => $workspace->updated_at,
                    ],
                    'invited_at' => $teamMember->created_at,
                    'status' => $teamMember->status,
                ];
            })
            ->values()
            ->toArray();

        return $pendingInvitations;
    }
}
