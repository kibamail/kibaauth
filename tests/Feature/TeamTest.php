<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use App\Models\Team;
use App\Models\Permission;
use App\Models\TeamMember;

beforeEach(function () {
    $this->user = User::factory()->create();

    // We'll create the client through OAuth helper to ensure consistency
    $this->workspace = null; // Will be set after OAuth client is created
});

// Helper function to setup OAuth and workspace
function setupWorkspaceAuth($user): array {
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
function createTeamInWorkspace($workspace, array $attributes = []): Team {
    return Team::factory()->create(array_merge([
        'workspace_id' => $workspace->id,
    ], $attributes));
}

describe('Team API', function () {
    /**
     * Test that verifies workspace owners can create teams in their workspaces.
     *
     * This test ensures that:
     * 1. Workspace owners have automatic permission to create teams
     * 2. Team creation request is processed successfully
     * 3. The team is properly stored in the database with correct attributes
     * 4. The response includes the created team data
     * 5. Auto-generated slug is properly created from team name
     *
     * @test
     */
    it('allows workspace owner to create team', function () {
        $oauthData = createOAuthHeadersForClient($this->user);
        $headers = $oauthData['headers'];
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $teamData = [
            'name' => 'Development Team',
            'description' => 'A team for developers',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Development Team',
                'description' => 'A team for developers',
                'slug' => 'development-team',
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Development Team',
            'description' => 'A team for developers',
            'workspace_id' => $workspace->id,
        ]);
    });

    /**
     * Test that verifies users with teams:create permission can create teams.
     *
     * This test ensures that:
     * 1. Users with teams:create permission can create teams even if they don't own the workspace
     * 2. Permission-based authorization works correctly for team creation
     * 3. Team creation is successful when proper permissions are granted
     * 4. The created team is properly associated with the workspace
     * 5. Permission validation works correctly for non-owner users
     *
     * @test
     */
    it('allows user with teams:create permission to create team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a different user who will have team creation permission
        $teamCreator = User::factory()->create();
        $creatorOauthData = createOAuthHeadersForClient($teamCreator, $client->id);
        $creatorHeaders = $creatorOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamCreator->id,
            'status' => 'active',
        ]);

        $teamData = [
            'name' => 'QA Team',
            'description' => 'Quality Assurance team',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $creatorHeaders);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'QA Team',
                'description' => 'Quality Assurance team',
                'slug' => 'qa-team',
            ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'QA Team',
            'description' => 'Quality Assurance team',
            'workspace_id' => $workspace->id,
        ]);
    });

    /**
     * Test that verifies users with teams:update permission can modify team details.
     *
     * This test ensures that:
     * 1. Users with teams:update permission can modify existing teams
     * 2. Team name and description can be updated successfully
     * 3. Permission-based authorization works for team updates
     * 4. Updated team data is properly saved to the database
     * 5. Non-owner users can update teams with appropriate permissions
     *
     * @test
     */
    it('allows user with teams:update permission to update team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team to update
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Original Team',
        ]);

        // Create a different user who will have team update permission
        $teamUpdater = User::factory()->create();
        $updaterOauthData = createOAuthHeadersForClient($teamUpdater, $client->id);
        $updaterHeaders = $updaterOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamUpdater->id,
            'status' => 'active',
        ]);

        $updateData = [
            'name' => 'Updated Team',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $updateData, $updaterHeaders);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Team',
                'description' => 'Updated description',
                'slug' => 'original-team', // Slug shouldn't change when not explicitly provided
            ]);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Team',
            'description' => 'Updated description',
            'workspace_id' => $workspace->id,
        ]);
    });

    /**
     * Test that verifies users with teams:delete permission can remove teams.
     *
     * This test ensures that:
     * 1. Users with teams:delete permission can delete existing teams
     * 2. Permission-based authorization works for team deletion
     * 3. Team deletion is processed successfully with proper permissions
     * 4. The team is completely removed from the database
     * 5. Non-owner users can delete teams with appropriate permissions
     *
     * @test
     */
    it('allows user with teams:delete permission to delete team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team to delete
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Team to Delete',
        ]);

        // Create a different user who will have team delete permission
        $teamDeleter = User::factory()->create();
        $deleterOauthData = createOAuthHeadersForClient($teamDeleter, $client->id);
        $deleterHeaders = $deleterOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamDeleter->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", [], $deleterHeaders);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Team deleted successfully',
            ]);

        $this->assertDatabaseMissing('teams', [
            'id' => $team->id,
        ]);
    });

    /**
     * Test that verifies users with teams:view permission can access team listings.
     *
     * This test ensures that:
     * 1. Users with teams:view permission can retrieve lists of teams
     * 2. Permission-based authorization works for team viewing
     * 3. All teams in the workspace are included in the response
     * 4. Team data includes necessary details like name, description, and slug
     * 5. Non-owner users can view teams with appropriate permissions
     *
     * @test
     */
    it('allows user with teams:view permission to view teams list', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a different user who will have team view permission
        $teamViewer = User::factory()->create();
        $viewerOauthData = createOAuthHeadersForClient($teamViewer, $client->id);
        $viewerHeaders = $viewerOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamViewer->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/teams", $viewerHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'workspace_id',
                        'permissions',
                    ],
                ],
            ]);

        // Should see at least the Administrators team
        $teams = $response->json('data');
        expect($teams)->toHaveCount(1); // Just the Administrators team
        expect($teams[0]['name'])->toBe('Administrators');
    });

    /**
     * Test that verifies users with teams:view permission can access individual team details.
     *
     * This test ensures that:
     * 1. Users with teams:view permission can retrieve specific team information
     * 2. Individual team endpoints are protected by permission-based authorization
     * 3. Complete team data is returned for authorized users
     * 4. Team permissions and relationships are included in the response
     * 5. Single team access works correctly with proper permissions
     *
     * @test
     */
    it('allows user with teams:view permission to view specific team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team to view
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Viewable Team',
        ]);

        // Create a different user who will have team view permission
        $teamViewer = User::factory()->create();
        $viewerOauthData = createOAuthHeadersForClient($teamViewer, $client->id);
        $viewerHeaders = $viewerOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamViewer->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $viewerHeaders);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $team->id,
                'name' => 'Viewable Team',
                'workspace_id' => $workspace->id,
            ]);
    });

    /**
     * Test that verifies users without teams:view permission cannot access team listings.
     *
     * This test ensures that:
     * 1. Users without teams:view permission are denied access to team lists
     * 2. Permission-based access control properly restricts unauthorized access
     * 3. Appropriate HTTP status codes are returned for unauthorized requests
     * 4. Security is maintained by preventing unauthorized team data access
     * 5. Permission validation works correctly for team list endpoints
     *
     * @test
     */
    it('prevents user without teams:view permission from viewing teams list', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a user with same client but no teams:view permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teams:view permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/teams", $unauthorizedHeaders);

        $response->assertStatus(403);
    });

    it('prevents user without teams:view permission from viewing specific team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team to view
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Specific Team',
        ]);

        // Create a user with same client but no teams:view permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teams:view permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $unauthorizedHeaders);

        $response->assertStatus(403);
    });

    it('prevents user without teams:update permission from updating team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team to update
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Original Team',
        ]);

        // Create a user with same client but no teams:update permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teams:update permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        $updateData = [
            'name' => 'Unauthorized Update',
        ];

        $response = $this->putJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $updateData, $unauthorizedHeaders);

        $response->assertStatus(403);
    });

    it('prevents user without teams:delete permission from deleting team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team to delete
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Team to Delete',
        ]);

        // Create a user with same client but no teams:delete permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teams:delete permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", [], $unauthorizedHeaders);

        $response->assertStatus(403);
    });

    /**
     * Test that verifies users without teams:create permission cannot create teams.
     *
     * This test ensures that:
     * 1. Users without teams:create permission are denied team creation access
     * 2. Permission-based access control prevents unauthorized team creation
     * 3. Appropriate HTTP status codes are returned for unauthorized requests
     * 4. Security is maintained by restricting team creation to authorized users
     * 5. Permission validation works correctly for team creation endpoints
     *
     * @test
     */
    it('prevents user without teams:create permission from creating team', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a user with same client but no teams:create permission
        $unauthorizedUser = User::factory()->create();
        $unauthorizedOauthData = createOAuthHeadersForClient($unauthorizedUser, $client->id);
        $unauthorizedHeaders = $unauthorizedOauthData['headers'];

        // Create a team without teams:create permission and add user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $unauthorizedUser->id,
            'status' => 'active',
        ]);

        $teamData = [
            'name' => 'Unauthorized Team Creation',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $unauthorizedHeaders);

        $response->assertStatus(403);
    });

    it('allows team creation with permissions', function () {
        extract(setupWorkspaceAuth($this->user));

        $permissions = Permission::factory()->count(3)->create([
            'client_id' => $client->id,
        ]);

        $teamData = [
            'name' => 'Development Team',
            'description' => 'A team for developers',
            'permission_ids' => $permissions->pluck('id')->toArray(),
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Development Team',
            ])
            ->assertJsonPath('data.permissions', function ($permissionsData) use ($permissions) {
                return count($permissionsData) === $permissions->count();
            });
    });

    it('prevents creating team with permissions from different client', function () {
        extract(setupWorkspaceAuth($this->user));

        $otherClient = Client::factory()->create();
        $validPermission = Permission::factory()->create(['client_id' => $client->id]);
        $invalidPermission = Permission::factory()->create(['client_id' => $otherClient->id]);

        $teamData = [
            'name' => 'Development Team',
            'permission_ids' => [$validPermission->id, $invalidPermission->id],
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids']);
    });

    it('lists teams in workspace', function () {
        extract(setupWorkspaceAuth($this->user));

        $teams = Team::factory()->count(3)->create([
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/teams", $headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'workspace_id',
                        'permissions',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect(count($responseData))->toBe(4); // 3 manual teams + 1 auto-created Administrators team
    });

    it('shows specific team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $permissions = Permission::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);
        $team->permissions()->attach($permissions);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'workspace_id',
                    'permissions' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                        ],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'id' => $team->id,
                'name' => $team->name,
                'workspace_id' => $workspace->id,
            ]);

        $responsePermissions = $response->json('data.permissions');
        expect(count($responsePermissions))->toBe(2);
    });

    it('allows workspace owner to update team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace, ['name' => 'Original Name']);

        $updateData = [
            'name' => 'Updated Team Name',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $updateData, $headers);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Team Name',
                'description' => 'Updated description',
            ]);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Team Name',
            'description' => 'Updated description',
        ]);
    });

    it('allows updating team permissions', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $initialPermissions = Permission::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);
        $team->permissions()->attach($initialPermissions);

        $newPermissions = Permission::factory()->count(3)->create([
            'client_id' => $client->id,
        ]);

        $updateData = [
            'permission_ids' => $newPermissions->pluck('id')->toArray(),
        ];

        $response = $this->putJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $updateData, $headers);

        $response->assertStatus(200);

        expect($team->fresh()->permissions()->count())->toBe(3);
    });

    it('allows workspace owner to delete team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", [], $headers);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Team deleted successfully',
            ]);

        $this->assertDatabaseMissing('teams', [
            'id' => $team->id,
        ]);
    });

    /**
     * Test that verifies non-workspace owners cannot create teams without explicit permissions.
     *
     * This test ensures that:
     * 1. Users who don't own the workspace cannot create teams by default
     * 2. Workspace ownership provides automatic team creation privileges
     * 3. Non-owners must have explicit teams:create permission to create teams
     * 4. Ownership-based authorization works correctly
     * 5. Default security model prevents unauthorized team creation
     *
     * @test
     */
    it('prevents non-workspace owner from creating team', function () {
        extract(setupWorkspaceAuth($this->user));

        // Create a user with same client but no teams:create permission
        $otherUser = User::factory()->create();
        $otherOauthData = createOAuthHeadersForClient($otherUser, $client->id);
        $headers = $otherOauthData['headers'];

        // Create a team without teams:create permission and add other user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $teamData = [
            'name' => 'Unauthorized Team',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(403);
    });

    it('prevents non-workspace owner from updating team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        // Create a user with same client but no teams:update permission
        $otherUser = User::factory()->create();
        $otherOauthData = createOAuthHeadersForClient($otherUser, $client->id);
        $headers = $otherOauthData['headers'];

        // Create a team without teams:update permission and add other user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $updateData = ['name' => 'Unauthorized Update'];

        $response = $this->putJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $updateData, $headers);

        $response->assertStatus(403);
    });

    it('prevents non-workspace owner from deleting team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        // Create a user with same client but no teams:delete permission
        $otherUser = User::factory()->create();
        $otherOauthData = createOAuthHeadersForClient($otherUser, $client->id);
        $headers = $otherOauthData['headers'];

        // Create a team without teams:delete permission and add other user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", [], $headers);

        $response->assertStatus(403);
    });

    it('prevents access to team from different workspace', function () {
        $oauthData = createOAuthHeadersForClient($this->user);
        $headers = $oauthData['headers'];
        $client = $oauthData['client'];

        $workspace1 = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $workspace2 = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace2->id,
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace1->id}/teams/{$team->id}", $headers);

        $response->assertStatus(404);
    });

    /**
     * Test that verifies team slugs are automatically generated from team names.
     *
     * This test ensures that:
     * 1. Team slugs are automatically created when not explicitly provided
     * 2. Slug generation follows proper URL-safe formatting rules
     * 3. The generated slug is based on the team name
     * 4. Auto-generation works correctly during team creation
     * 5. Users don't need to manually specify slugs for teams
     *
     * @test
     */
    it('auto-generates team slug', function () {
        extract(setupWorkspaceAuth($this->user));

        $teamData = [
            'name' => 'Test Team Name',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'slug' => 'test-team-name',
            ]);
    });

    it('ensures team slug uniqueness within workspace', function () {
        extract(setupWorkspaceAuth($this->user));

        createTeamInWorkspace($workspace, ['slug' => 'test-team']);

        $teamData = [
            'name' => 'Test Team',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(201);
        $responseData = $response->json('data');
        expect($responseData['slug'])->not->toBe('test-team');
    });

    it('includes teams with permissions when fetching workspace', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $permissions = Permission::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);
        $team->permissions()->attach($permissions);

        $response = $this->getJson("/api/workspaces/{$workspace->id}", $headers);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'teams' => [
                        '*' => [
                            'id',
                            'name',
                            'permissions' => [
                                '*' => [
                                    'id',
                                    'name',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $responseTeams = $response->json('data.teams');
        expect(count($responseTeams))->toBe(2); // 1 manual team + 1 auto-created Administrators team

        // Find the manually created team (not Administrators)
        $manualTeam = collect($responseTeams)->firstWhere('name', '!=', 'Administrators');
        expect($manualTeam)->not->toBeNull();
        expect(count($manualTeam['permissions']))->toBe(2);

        // Verify Administrators team exists and has default permissions
        $adminTeam = collect($responseTeams)->firstWhere('name', 'Administrators');
        expect($adminTeam)->not->toBeNull();
        expect(count($adminTeam['permissions']))->toBe(8); // 8 default permissions
    });

    it('validates team name is required', function () {
        extract(setupWorkspaceAuth($this->user));

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", [], $headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('validates team slug format', function () {
        extract(setupWorkspaceAuth($this->user));

        $teamData = [
            'name' => 'Test Team',
            'slug' => 'invalid slug with spaces',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    });

    it('removes permission associations when team is deleted', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $permissions = Permission::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);
        $team->permissions()->attach($permissions);

        $this->assertDatabaseHas('team_permission', [
            'team_id' => $team->id,
        ]);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", [], $headers);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('team_permission', [
            'team_id' => $team->id,
        ]);
    });

    /**
     * Test that verifies team permissions can be synchronized/updated.
     *
     * This test ensures that:
     * 1. Team permissions can be updated by providing a new set of permission IDs
     * 2. The sync operation replaces existing permissions with the new set
     * 3. Permission synchronization works correctly for workspace owners
     * 4. Team permission relationships are properly managed
     * 5. Bulk permission updates work as expected
     *
     * @test
     */
    it('syncs team permissions', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $initialPermissions = Permission::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);
        $team->permissions()->attach($initialPermissions);

        $newPermissions = Permission::factory()->count(3)->create([
            'client_id' => $client->id,
        ]);

        $syncData = [
            'permission_ids' => $newPermissions->pluck('id')->toArray(),
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/sync-permissions", $syncData, $headers);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Team permissions synced successfully',
            ]);

        $team->refresh();
        expect($team->permissions()->count())->toBe(3);

        $teamPermissionIds = $team->permissions()->pluck('permissions.id')->sort()->values()->toArray();
        $expectedPermissionIds = $newPermissions->pluck('id')->sort()->values()->toArray();
        expect($teamPermissionIds)->toBe($expectedPermissionIds);
    });

    it('prevents syncing permissions from different client', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $otherClient = Client::factory()->create();
        $validPermission = Permission::factory()->create(['client_id' => $client->id]);
        $invalidPermission = Permission::factory()->create(['client_id' => $otherClient->id]);

        $syncData = [
            'permission_ids' => [$validPermission->id, $invalidPermission->id],
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/sync-permissions", $syncData, $headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids']);
    });

    it('prevents non-workspace owner from syncing permissions', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $permissions = Permission::factory()->count(2)->create([
            'client_id' => $client->id,
        ]);

        // Create a user with same client but no teams:update permission
        $otherUser = User::factory()->create();
        $otherOauthData = createOAuthHeadersForClient($otherUser, $client->id);
        $headers = $otherOauthData['headers'];

        // Create a team without teams:update permission and add other user to it
        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $syncData = [
            'permission_ids' => $permissions->pluck('id')->toArray(),
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/sync-permissions", $syncData, $headers);

        $response->assertStatus(403);
    });
});
