<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Permission;
use App\Models\Client;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// Helper function to setup OAuth and workspace
function setupUserTestAuth($user): array {
    $oauthData = createOAuthHeadersForClient($user);
    $headers = $oauthData['headers'];
    $client = $oauthData['client'];

    return compact('headers', 'client');
}

describe('User API', function () {
    it('returns authenticated user with owned workspaces and all nested data', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create workspace owned by user
        $workspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Create team in workspace
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Create permissions for the team
        $permission1 = Permission::factory()->create(['client_id' => $client->id]);
        $permission2 = Permission::factory()->create(['client_id' => $client->id]);
        $team->permissions()->attach([$permission1->id, $permission2->id]);

        // Create team members
        $memberUser1 = User::factory()->create();
        $memberUser2 = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser1->id,
            'status' => 'active',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $memberUser2->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                    'workspaces' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'user_id',
                            'client_id',
                            'created_at',
                            'updated_at',
                            'teams' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'description',
                                    'slug',
                                    'workspace_id',
                                    'created_at',
                                    'updated_at',
                                    'team_members' => [
                                        '*' => [
                                            'id',
                                            'team_id',
                                            'user_id',
                                            'email',
                                            'status',
                                            'created_at',
                                            'updated_at',
                                            'user' => [
                                                'id',
                                                'email',
                                                'email_verified_at',
                                                'created_at',
                                                'updated_at',
                                            ]
                                        ]
                                    ],
                                    'permissions' => [
                                        '*' => [
                                            'id',
                                            'name',
                                            'description',
                                            'slug',
                                            'client_id',
                                            'created_at',
                                            'updated_at',
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'workspaces' => [
                        [
                            'id' => $workspace->id,
                            'name' => $workspace->name,
                            'user_id' => $this->user->id,
                            'client_id' => $client->id,
                        ]
                    ]
                ]
            ]);

        // Verify we have 2 teams (1 manual + 1 auto-created Administrators)
        $responseTeams = $response->json('data.workspaces.0.teams');
        expect($responseTeams)->toHaveCount(2);

        // Find the manually created team
        $manualTeam = collect($responseTeams)->firstWhere('id', $team->id);
        expect($manualTeam)->not->toBeNull();

        // Verify team members are included in the manual team
        expect($manualTeam['team_members'])->toHaveCount(2);

        // Verify permissions are included in the manual team
        expect($manualTeam['permissions'])->toHaveCount(2);

        // Verify Administrators team exists and has 8 default permissions
        $adminTeam = collect($responseTeams)->firstWhere('name', 'Administrators');
        expect($adminTeam)->not->toBeNull();
        expect($adminTeam['permissions'])->toHaveCount(8);
    });

    it('returns authenticated user with workspaces where they are team members', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create another user who owns a workspace
        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create team in that workspace
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Add our user as a team member
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'workspaces' => [
                        [
                            'id' => $workspace->id,
                            'name' => $workspace->name,
                            'user_id' => $workspaceOwner->id,
                            'client_id' => $client->id,
                        ]
                    ]
                ]
            ]);

        // Verify we have 2 teams (1 manual + 1 auto-created Administrators)
        $responseTeams = $response->json('data.workspaces.0.teams');
        expect($responseTeams)->toHaveCount(2);

        // Find the manually created team
        $manualTeam = collect($responseTeams)->firstWhere('id', $team->id);
        expect($manualTeam)->not->toBeNull();
        expect($manualTeam['name'])->toBe($team->name);

        // Verify Administrators team exists
        $adminTeam = collect($responseTeams)->firstWhere('name', 'Administrators');
        expect($adminTeam)->not->toBeNull();
    });

    it('combines owned workspaces and member workspaces without duplicates', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create workspace owned by user
        $ownedWorkspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Create team in owned workspace
        $ownedTeam = Team::factory()->create([
            'workspace_id' => $ownedWorkspace->id,
        ]);

        // Add user as member to their own team (should not duplicate workspace)
        TeamMember::factory()->create([
            'team_id' => $ownedTeam->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // Create another workspace where user is just a member
        $workspaceOwner = User::factory()->create();
        $memberWorkspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        $memberTeam = Team::factory()->create([
            'workspace_id' => $memberWorkspace->id,
        ]);

        TeamMember::factory()->create([
            'team_id' => $memberTeam->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(2);

        // Find workspaces by ID
        $returnedOwnedWorkspace = collect($workspaces)->firstWhere('id', $ownedWorkspace->id);
        $returnedMemberWorkspace = collect($workspaces)->firstWhere('id', $memberWorkspace->id);

        expect($returnedOwnedWorkspace)->not->toBeNull();
        expect($returnedMemberWorkspace)->not->toBeNull();
    });

    it('only returns workspaces for the current client', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create workspace for current client
        $workspaceCurrentClient = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        // Create workspace for different client
        $differentClient = Client::factory()->create();
        $workspaceDifferentClient = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $differentClient->id,
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);
        expect($workspaces[0]['id'])->toBe($workspaceCurrentClient->id);
        expect($workspaces[0]['client_id'])->toBe($client->id);
    });

    it('includes email-only team members', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        // Create regular team member
        $regularUser = User::factory()->create();
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $regularUser->id,
            'status' => 'active',
        ]);

        // Create email-only team member
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'email' => 'invited@example.com',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        // Find the manual team (not Administrators team)
        $responseTeams = $response->json('data.workspaces.0.teams');
        $manualTeam = collect($responseTeams)->firstWhere('id', $team->id);
        expect($manualTeam)->not->toBeNull();

        $teamMembers = $manualTeam['team_members'];
        expect($teamMembers)->toHaveCount(2);

        $emailOnlyMember = collect($teamMembers)->firstWhere('email', 'invited@example.com');
        expect($emailOnlyMember)->not->toBeNull();
        expect($emailOnlyMember['user_id'])->toBeNull();
        expect($emailOnlyMember['user'])->toBeNull();
    });

    it('does not include sensitive user data like password', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonMissing(['password', 'remember_token'])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ]
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    });

    it('returns empty workspaces array when user has no workspaces', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'workspaces' => []
                ]
            ]);
    });

    it('includes all teams in workspace even if user is only member of one team', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create workspace owned by another user
        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create two teams
        $team1 = Team::factory()->create(['workspace_id' => $workspace->id]);
        $team2 = Team::factory()->create(['workspace_id' => $workspace->id]);

        // User is only member of team1
        TeamMember::factory()->create([
            'team_id' => $team1->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        // But another user is member of team2
        $otherUser = User::factory()->create();
        TeamMember::factory()->create([
            'team_id' => $team2->id,
            'user_id' => $otherUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $teams = $response->json('data.workspaces.0.teams');
        expect($teams)->toHaveCount(3); // 2 manual teams + 1 auto-created Administrators team

        $teamIds = collect($teams)->pluck('id')->toArray();
        expect($teamIds)->toContain($team1->id);
        expect($teamIds)->toContain($team2->id);
    });
});
