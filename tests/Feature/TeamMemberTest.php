<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Client;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// Helper function to setup OAuth and workspace
function setupWorkspaceAuthForTeamMembers($user): array {
    $oauthData = createOAuthHeadersForClient($user);
    $headers = $oauthData['headers'];
    $client = $oauthData['client'];

    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'client_id' => $client->id,
    ]);

    return compact('headers', 'client', 'workspace');
}

// Helper function to create team in workspace
function createTeamInWorkspaceForMembers($workspace, array $attributes = []): Team {
    return Team::factory()->create(array_merge([
        'workspace_id' => $workspace->id,
    ], $attributes));
}

describe('Team Member API', function () {
    it('allows workspace owner to create team member', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Team member added successfully',
                'data' => [
                    'team_id' => $team->id,
                    'user_id' => $newUser->id,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $newUser->id,
            'status' => 'active',
        ]);
    });

    it('allows workspace owner to create team member with pending status', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'pending',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Team member added successfully',
                'data' => [
                    'team_id' => $team->id,
                    'user_id' => $newUser->id,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $newUser->id,
            'status' => 'pending',
        ]);
    });

    it('defaults to pending status when status is not provided', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Team member added successfully',
                'data' => [
                    'team_id' => $team->id,
                    'user_id' => $newUser->id,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $newUser->id,
            'status' => 'pending',
        ]);
    });

    it('includes user data in response', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'team_id',
                    'user_id',
                    'status',
                    'created_at',
                    'updated_at',
                    'user' => [
                        'id',
                        'email',
                    ],
                ],
            ]);
    });

    it('prevents creating duplicate team member', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        // Create first team member
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $newUser->id,
            'status' => 'active',
        ]);

        // Try to create duplicate
        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'pending',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    });

    it('prevents non-workspace owner from creating team member', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $workspace = $oauthData['workspace'];
        $client = $oauthData['client'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        // Create another user who is not the workspace owner but uses same client
        $otherUser = User::factory()->create();
        $otherOauthData = createOAuthHeadersForClient($otherUser, $client->id);
        $otherHeaders = $otherOauthData['headers'];

        $response = $this->withHeaders($otherHeaders)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(403);
    });

    it('prevents access to team from different workspace', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $client = $oauthData['client'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        // Create another workspace with different owner
        $otherWorkspace = Workspace::factory()->create([
            'client_id' => $client->id,
        ]);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$otherWorkspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(403);
    });

    it('prevents access to workspace from different client', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        // Create another client and workspace
        $otherOauthData = createOAuthHeadersForClient($this->user);
        $otherClient = $otherOauthData['client'];
        $otherWorkspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $otherClient->id,
        ]);
        $otherTeam = createTeamInWorkspaceForMembers($otherWorkspace);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$otherWorkspace->id}/teams/{$otherTeam->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(404);
    });

    it('validates required user_id field', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    });

    it('validates user_id exists in users table', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => 99999, // Non-existent user ID
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    });

    it('validates status field values', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    it('returns 404 when team does not exist', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/99999/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(404);
    });

    it('returns 404 when workspace does not exist', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/99999/teams/{$team->id}/members", [
                'user_id' => $newUser->id,
                'status' => 'active',
            ]);

        $response->assertStatus(404);
    });

    it('allows workspace owner to add themselves as team member', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $this->user->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Team member added successfully',
                'data' => [
                    'team_id' => $team->id,
                    'user_id' => $this->user->id,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);
    });

    it('allows creating team member using email for existing user', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $existingUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'email' => $existingUser->email,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Team member added successfully',
                'data' => [
                    'team_id' => $team->id,
                    'user_id' => $existingUser->id,
                    'email' => null,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $existingUser->id,
            'email' => null,
            'status' => 'active',
        ]);
    });

    it('allows creating email-only team member for non-existing user', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $inviteEmail = 'invite@example.com';

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'email' => $inviteEmail,
                'status' => 'pending',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Team member added successfully',
                'data' => [
                    'team_id' => $team->id,
                    'user_id' => null,
                    'email' => $inviteEmail,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => null,
            'email' => $inviteEmail,
            'status' => 'pending',
        ]);
    });

    it('prevents creating duplicate team member using existing user email', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $existingUser = User::factory()->create();

        // Create first team member using user_id
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $existingUser->id,
            'email' => null,
            'status' => 'active',
        ]);

        // Try to create duplicate using email
        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'email' => $existingUser->email,
                'status' => 'pending',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('prevents creating duplicate email invitation', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $inviteEmail = 'invite@example.com';

        // Create first email invitation
        TeamMember::factory()->withEmail($inviteEmail)->create([
            'team_id' => $team->id,
            'status' => 'pending',
        ]);

        // Try to create duplicate email invitation
        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'email' => $inviteEmail,
                'status' => 'pending',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates that either user_id or email must be provided', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'email']);
    });

    it('validates that both user_id and email cannot be provided', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $existingUser = User::factory()->create();

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'user_id' => $existingUser->id,
                'email' => 'test@example.com',
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'email']);
    });

    it('validates email format when email is provided', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'email' => 'invalid-email',
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('does not include user data in response for email-only members', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $inviteEmail = 'invite@example.com';

        $response = $this->withHeaders($headers)
            ->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
                'email' => $inviteEmail,
                'status' => 'pending',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'team_id',
                    'user_id',
                    'email',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonMissing(['user']);
    });

    it('requires authentication', function () {
        $user = User::factory()->create();
        $client = \App\Models\Client::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $team = createTeamInWorkspaceForMembers($workspace);
        $newUser = User::factory()->create();

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
            'user_id' => $newUser->id,
            'status' => 'active',
        ]);

        $response->assertStatus(401);
    });

    it('allows workspace owner to remove team member', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);
        $memberUser = User::factory()->create();

        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($headers)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Team member removed successfully',
            ]);

        $this->assertDatabaseMissing('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    it('allows team member to remove themselves', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $client = $oauthData['client'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        // Create another user who will be the team member
        $memberUser = User::factory()->create();
        $memberOauthData = createOAuthHeadersForClient($memberUser, $client->id);
        $memberHeaders = $memberOauthData['headers'];

        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($memberHeaders)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Team member removed successfully',
            ]);

        $this->assertDatabaseMissing('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    it('prevents non-authorized user from removing team member', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $client = $oauthData['client'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        // Create two other users
        $memberUser = User::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($unauthorizedHeaders)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    it('prevents removing team member from different workspace', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $client = $oauthData['client'];

        // Create two workspaces
        $workspace1 = $oauthData['workspace'];
        $workspace2 = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $team1 = createTeamInWorkspaceForMembers($workspace1);
        $team2 = createTeamInWorkspaceForMembers($workspace2);

        $memberUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team2->id,
            'user_id' => $memberUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($headers)
            ->deleteJson("/api/workspaces/{$workspace1->id}/teams/{$team1->id}/members/{$teamMember->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    it('prevents removing team member from different client', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        // Create different client and headers
        $differentOauthData = createOAuthHeadersForClient($this->user);
        $differentHeaders = $differentOauthData['headers'];

        $memberUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($differentHeaders)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    it('returns 404 when team member does not exist', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $nonExistentId = (string) \Illuminate\Support\Str::uuid();

        $response = $this->withHeaders($headers)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$nonExistentId}");

        $response->assertStatus(404);
    });

    it('requires authentication for delete', function () {
        $user = User::factory()->create();
        $client = \App\Models\Client::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
        $team = createTeamInWorkspaceForMembers($workspace);
        $memberUser = User::factory()->create();

        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}");

        $response->assertStatus(401);
    });
});
