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
    /**
     * Test that verifies workspace owners can add team members to teams.
     *
     * This test ensures that:
     * 1. Workspace owners have automatic permission to add team members
     * 2. Team member creation request is processed successfully
     * 3. The team member is properly stored in the database with correct attributes
     * 4. The response includes the created team member data with user information
     * 5. Team membership relationships are correctly established
     *
     * @test
     */
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

    /**
     * Test that verifies users with teamMembers:create permission can add team members.
     *
     * This test ensures that:
     * 1. Users with teamMembers:create permission can add members even if they don't own the workspace
     * 2. Permission-based authorization works correctly for team member creation
     * 3. Team member addition is successful when proper permissions are granted
     * 4. The created team member is properly associated with the team
     * 5. Permission validation works correctly for non-owner users
     *
     * @test
     */
    it('allows user with teamMembers:create permission to create team member', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Create a different user who will have team member creation permission
        $memberCreator = User::factory()->create();
        $creatorOauthData = createOAuthHeadersForClient($memberCreator, $client->id);
        $creatorHeaders = $creatorOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $memberCreator->id,
            'status' => 'active',
        ]);

        // User to be added as team member
        $targetUser = User::factory()->create();

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
            'user_id' => $targetUser->id,
            'status' => 'active',
        ], $creatorHeaders);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'user_id' => $targetUser->id,
                'status' => 'active',
                'team_id' => $team->id,
            ]);

        $this->assertDatabaseHas('team_members', [
            'user_id' => $targetUser->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);
    });

    /**
     * Test that verifies users with teamMembers:delete permission can remove team members.
     *
     * This test ensures that:
     * 1. Users with teamMembers:delete permission can remove existing team members
     * 2. Permission-based authorization works for team member deletion
     * 3. Team member removal is processed successfully with proper permissions
     * 4. The team member is completely removed from the database
     * 5. Non-owner users can delete team members with appropriate permissions
     *
     * @test
     */
    it('allows user with teamMembers:delete permission to delete team member', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Create target user to be removed
        $targetUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $targetUser->id,
            'status' => 'active',
        ]);

        // Create a different user who will have team member delete permission
        $memberDeleter = User::factory()->create();
        $deleterOauthData = createOAuthHeadersForClient($memberDeleter, $client->id);
        $deleterHeaders = $deleterOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $memberDeleter->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}", [], $deleterHeaders);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Team member removed successfully',
            ]);

        $this->assertDatabaseMissing('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    /**
     * Test that verifies users without teamMembers:create permission cannot add team members.
     *
     * This test ensures that:
     * 1. Users without teamMembers:create permission are denied team member creation access
     * 2. Permission-based access control prevents unauthorized team member addition
     * 3. Appropriate HTTP status codes are returned for unauthorized requests
     * 4. Security is maintained by restricting team member creation to authorized users
     * 5. Permission validation works correctly for team member creation endpoints
     *
     * @test
     */
    it('prevents user without teamMembers:create permission from creating team member', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Create a user with same client but no teamMembers:create permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teamMembers:create permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        // User to be added as team member
        $targetUser = User::factory()->create();

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members", [
            'user_id' => $targetUser->id,
            'status' => 'active',
        ], $unauthorizedHeaders);

        $response->assertStatus(403);
    });

    /**
     * Test that verifies users without teamMembers:delete permission cannot remove team members.
     *
     * This test ensures that:
     * 1. Users without teamMembers:delete permission are denied team member deletion access
     * 2. Permission-based access control prevents unauthorized team member removal
     * 3. Appropriate HTTP status codes are returned for unauthorized requests
     * 4. Security is maintained by restricting team member deletion to authorized users
     * 5. Permission validation works correctly for team member deletion endpoints
     *
     * @test
     */
    it('prevents user without teamMembers:delete permission from deleting team member', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Create target user to be removed
        $targetUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $targetUser->id,
            'status' => 'active',
        ]);

        // Create a user with same client but no teamMembers:delete permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teamMembers:delete permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}", [], $unauthorizedHeaders);

        $response->assertStatus(403);
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

    /**
     * Test that verifies workspace owners can remove team members from teams.
     *
     * This test ensures that:
     * 1. Workspace owners have automatic permission to remove team members
     * 2. Team member removal request is processed successfully
     * 3. The team member is properly removed from the database
     * 4. Ownership-based authorization works for team member management
     * 5. Team membership relationships are correctly terminated
     *
     * @test
     */
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

    /**
     * Test that verifies team members can remove themselves from teams.
     *
     * This test ensures that:
     * 1. Team members can leave teams by removing themselves
     * 2. Self-removal is allowed regardless of other permissions
     * 3. Users can manage their own team membership
     * 4. Self-removal request is processed successfully
     * 5. Team membership is properly terminated when users leave
     *
     * @test
     */
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

    /**
     * Test that verifies unauthorized users cannot remove team members.
     *
     * This test ensures that:
     * 1. Users without proper permissions cannot remove other team members
     * 2. Access control prevents unauthorized team member removal
     * 3. Only authorized users can manage team membership
     * 4. Security is maintained by restricting team member management
     * 5. Permission validation works correctly for team member operations
     *
     * @test
     */
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

    /**
     * Test that verifies users can accept their own team membership invitations.
     *
     * This test ensures that:
     * 1. Users can accept pending invitations for teams they were invited to
     * 2. The invitation status changes from pending to active
     * 3. Only the invited user can accept their own invitation
     * 4. The response includes updated team member data
     * 5. Invitation acceptance is properly processed and stored
     *
     * @test
     */
    it('allows user to accept their own team invitation', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'pending',
        ]);

        $invitedUserOauth = createOAuthHeadersForClient($invitedUser, $oauthData['client']->id);
        $invitedUserHeaders = $invitedUserOauth['headers'];

        $response = $this->withHeaders($invitedUserHeaders)
            ->patchJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Team invitation accepted successfully',
                'data' => [
                    'id' => $teamMember->id,
                    'team_id' => $team->id,
                    'user_id' => $invitedUser->id,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
            'status' => 'active',
        ]);
    });

    /**
     * Test that verifies users can reject their own team membership invitations.
     *
     * This test ensures that:
     * 1. Users can reject pending invitations for teams they were invited to
     * 2. The team member record is completely removed from the database
     * 3. Only the invited user can reject their own invitation
     * 4. Invitation rejection is properly processed
     * 5. The team member relationship is terminated
     *
     * @test
     */
    it('allows user to reject their own team invitation', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'pending',
        ]);

        $invitedUserOauth = createOAuthHeadersForClient($invitedUser, $oauthData['client']->id);
        $invitedUserHeaders = $invitedUserOauth['headers'];

        $response = $this->withHeaders($invitedUserHeaders)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/reject");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Team invitation rejected successfully',
            ]);

        $this->assertDatabaseMissing('team_members', [
            'id' => $teamMember->id,
        ]);
    });

    /**
     * Test that verifies only the invited user can accept their invitation.
     *
     * This test ensures that:
     * 1. Other users cannot accept invitations intended for different users
     * 2. Authorization is properly enforced for invitation acceptance
     * 3. Appropriate error responses are returned for unauthorized attempts
     * 4. Invitation security is maintained
     * 5. User isolation is enforced for invitation management
     *
     * @test
     */
    it('prevents other users from accepting someone elses invitation', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'pending',
        ]);

        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauth = createOAuthHeadersForClient($unauthorizedUser, $oauthData['client']->id);
        $unauthorizedHeaders = $unauthorizedOauth['headers'];

        $response = $this->withHeaders($unauthorizedHeaders)
            ->patchJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/accept");

        $response->assertStatus(403);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
            'status' => 'pending',
        ]);
    });

    /**
     * Test that verifies only the invited user can reject their invitation.
     *
     * This test ensures that:
     * 1. Other users cannot reject invitations intended for different users
     * 2. Authorization is properly enforced for invitation rejection
     * 3. Appropriate error responses are returned for unauthorized attempts
     * 4. Invitation security is maintained
     * 5. User isolation is enforced for invitation management
     *
     * @test
     */
    it('prevents other users from rejecting someone elses invitation', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'pending',
        ]);

        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauth = createOAuthHeadersForClient($unauthorizedUser, $oauthData['client']->id);
        $unauthorizedHeaders = $unauthorizedOauth['headers'];

        $response = $this->withHeaders($unauthorizedHeaders)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/reject");

        $response->assertStatus(403);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
            'status' => 'pending',
        ]);
    });

    /**
     * Test that verifies active memberships cannot be accepted again.
     *
     * This test ensures that:
     * 1. Active team memberships cannot be accepted again
     * 2. Appropriate error messages are returned for invalid operations
     * 3. Status validation prevents incorrect state transitions
     * 4. Business logic prevents duplicate acceptance
     * 5. Data integrity is maintained
     *
     * @test
     */
    it('prevents accepting already active memberships', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'active',
        ]);

        $memberOauth = createOAuthHeadersForClient($invitedUser, $oauthData['client']->id);
        $memberHeaders = $memberOauth['headers'];

        $response = $this->withHeaders($memberHeaders)
            ->patchJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/accept");

        $response->assertStatus(400);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
            'status' => 'active',
        ]);
    });

    /**
     * Test that verifies active memberships cannot be rejected.
     *
     * This test ensures that:
     * 1. Active team memberships cannot be rejected via the rejection endpoint
     * 2. Appropriate error messages are returned for invalid operations
     * 3. Status validation prevents incorrect state transitions
     * 4. Business logic separates rejection from leaving active teams
     * 5. Data integrity is maintained
     *
     * @test
     */
    it('prevents rejecting already active memberships', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $headers = $oauthData['headers'];
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'active',
        ]);

        $memberOauth = createOAuthHeadersForClient($invitedUser, $oauthData['client']->id);
        $memberHeaders = $memberOauth['headers'];

        $response = $this->withHeaders($memberHeaders)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/reject");

        $response->assertStatus(400);

        $this->assertDatabaseHas('team_members', [
            'id' => $teamMember->id,
            'status' => 'active',
        ]);
    });

    /**
     * Test that verifies invitation endpoints require authentication.
     *
     * This test ensures that:
     * 1. Unauthenticated requests to invitation endpoints are rejected
     * 2. Authentication middleware protects invitation management
     * 3. Appropriate HTTP status codes are returned for unauthorized access
     * 4. Security is enforced for all invitation operations
     * 5. Authentication is required for both acceptance and rejection
     *
     * @test
     */
    it('requires authentication for invitation endpoints', function () {
        $oauthData = setupWorkspaceAuthForTeamMembers($this->user);
        $workspace = $oauthData['workspace'];
        $team = createTeamInWorkspaceForMembers($workspace);

        $invitedUser = User::factory()->create();
        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $invitedUser->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/accept");
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/reject");
        $response->assertStatus(401);
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
