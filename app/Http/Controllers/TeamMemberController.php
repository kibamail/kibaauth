<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamMemberController extends Controller
{
    /**
     * Store a newly created team member in storage.
     */
    public function store(StoreTeamMemberRequest $request, Workspace $workspace, Team $team): JsonResponse
    {
        // Authorize that the user is the workspace owner
        $this->authorize('update', $workspace);

        $clientId = $this->getClientId($request);

        // Verify workspace belongs to the client
        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        // Verify team belongs to the workspace
        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }

        $validated = $request->validated();

        $userId = null;
        $email = null;

        // Handle user_id or email-based team member creation
        if (!empty($validated['user_id'])) {
            // User ID provided - check if user exists
            $user = User::find($validated['user_id']);
            if (!$user) {
                abort(404, 'User not found');
            }
            $userId = $validated['user_id'];

            // Check if user is already a member of this team
            $existingMember = TeamMember::where('team_id', $team->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingMember) {
                throw ValidationException::withMessages([
                    'user_id' => ['This user is already a member of this team.']
                ]);
            }
        } else {
            // Email provided - check if user exists with this email
            $email = $validated['email'];
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                // User exists - use their user_id
                $userId = $existingUser->id;
                $email = null; // Don't store email when we have user_id

                // Check if user is already a member of this team
                $existingMember = TeamMember::where('team_id', $team->id)
                    ->where('user_id', $userId)
                    ->first();

                if ($existingMember) {
                    throw ValidationException::withMessages([
                        'email' => ['A user with this email is already a member of this team.']
                    ]);
                }
            } else {
                // User doesn't exist - check if email is already invited
                $existingMember = TeamMember::where('team_id', $team->id)
                    ->where('email', $email)
                    ->first();

                if ($existingMember) {
                    throw ValidationException::withMessages([
                        'email' => ['This email has already been invited to this team.']
                    ]);
                }
            }
        }

        // Create the team member
        $teamMember = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $userId,
            'email' => $email,
            'status' => $validated['status'] ?? 'pending',
        ]);

        // Load the user relationship for response (if user exists)
        if ($teamMember->user_id) {
            $teamMember->load('user');
        }

        return response()->json([
            'data' => $teamMember,
            'message' => 'Team member added successfully',
        ], 201);
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
