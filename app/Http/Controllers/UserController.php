<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            ->with([
                'teams.teamMembers.user',
                'teams.permissions'
            ])
            ->get();

        // Get workspaces where the user is a team member for this client
        $memberWorkspaces = collect();

        // Find all team memberships for this user
        $teamMemberships = $user->teamMembers()
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

        // Group team memberships by workspace
        $memberWorkspaceGroups = $teamMemberships->groupBy('team.workspace.id');

        foreach ($memberWorkspaceGroups as $workspaceId => $memberships) {
            $workspace = $memberships->first()->team->workspace;

            // Load all teams for this workspace (not just the ones the user is a member of)
            $workspace->load([
                'teams.teamMembers.user',
                'teams.permissions'
            ]);

            $memberWorkspaces->push($workspace);
        }

        // Merge owned and member workspaces, removing duplicates
        $allWorkspaces = $ownedWorkspaces->merge($memberWorkspaces)->unique('id')->values();

        // Load the user data without sensitive information
        $userData = $user->only(['id', 'email', 'email_verified_at', 'created_at', 'updated_at']);

        return response()->json([
            'data' => array_merge($userData, [
                'workspaces' => $allWorkspaces
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
}
