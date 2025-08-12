<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use App\Models\Team;
use App\Models\Permission;

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
        expect(count($responseData))->toBe(3);
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

    it('prevents non-workspace owner from creating team', function () {
        extract(setupWorkspaceAuth($this->user));

        $otherUser = User::factory()->create();

        $otherOauthData = createOAuthHeadersForClient($otherUser);
        $headers = $otherOauthData['headers'];

        $teamData = [
            'name' => 'Unauthorized Team',
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams", $teamData, $headers);

        $response->assertStatus(403);
    });

    it('prevents non-workspace owner from updating team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $otherUser = User::factory()->create();

        $otherOauthData = createOAuthHeadersForClient($otherUser);
        $headers = $otherOauthData['headers'];

        $updateData = ['name' => 'Unauthorized Update'];

        $response = $this->putJson("/api/workspaces/{$workspace->id}/teams/{$team->id}", $updateData, $headers);

        $response->assertStatus(403);
    });

    it('prevents non-workspace owner from deleting team', function () {
        extract(setupWorkspaceAuth($this->user));

        $team = createTeamInWorkspace($workspace);

        $otherUser = User::factory()->create();

        $otherOauthData = createOAuthHeadersForClient($otherUser);
        $headers = $otherOauthData['headers'];

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
        expect(count($responseTeams))->toBe(1);
        expect(count($responseTeams[0]['permissions']))->toBe(2);
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

        $otherUser = User::factory()->create();
        $otherOauthData = createOAuthHeadersForClient($otherUser);
        $headers = $otherOauthData['headers'];

        $syncData = [
            'permission_ids' => $permissions->pluck('id')->toArray(),
        ];

        $response = $this->postJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/sync-permissions", $syncData, $headers);

        $response->assertStatus(403);
    });
});
