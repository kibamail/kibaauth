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
    /**
     * Test that verifies authenticated users can retrieve their owned workspaces with complete nested data.
     *
     * This test ensures that:
     * 1. An authenticated user can access the /api/user endpoint
     * 2. Workspaces owned by the user are included in the response
     * 3. Teams within workspaces are properly loaded with permissions
     * 4. Team members are included with user data when user has appropriate permissions
     * 5. All nested relationships are correctly eager-loaded
     * 6. Workspace owners have full access to all data regardless of explicit permissions
     *
     * @test
     */
    it('returns authenticated user with owned workspaces and all nested data', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create workspace owned by user
        $workspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);


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
            'status' => 'active',
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

    /**
     * Test that verifies authenticated users can see workspaces where they are team members.
     *
     * This test ensures that:
     * 1. Users can access workspaces through team membership even if they don't own them
     * 2. Workspaces from other owners are included when user is a team member
     * 3. Basic workspace information is visible to team members
     * 4. Teams are not visible without appropriate permissions
     * 5. The workspace appears in the user's workspace list
     *
     * @test
     */
    it('returns authenticated user with workspaces where they are team members', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];


        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);


        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);


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


        $responseTeams = $response->json('data.workspaces.0.teams');
        expect($responseTeams)->toHaveCount(0);
    });

    /**
     * Test that verifies owned and member workspaces are combined without duplicates.
     *
     * This test ensures that:
     * 1. Users who both own and are members of the same workspace see it only once
     * 2. The workspace deduplication logic works correctly
     * 3. Both owned and member workspaces are included in the response
     * 4. No duplicate workspace entries appear in the final result
     * 5. The workspace list is properly unified and unique
     *
     * @test
     */
    it('combines owned workspaces and member workspaces without duplicates', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];


        $ownedWorkspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);


        $ownedTeam = Team::factory()->create([
            'workspace_id' => $ownedWorkspace->id,
        ]);

        // Add user as member to their own team (should not duplicate workspace)
        TeamMember::factory()->create([
            'team_id' => $ownedTeam->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);


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

    /**
     * Test that verifies client isolation - only workspaces for the current client are returned.
     *
     * This test ensures that:
     * 1. Users only see workspaces associated with their current OAuth client
     * 2. Workspaces from other clients are not visible
     * 3. Client isolation is properly enforced for security
     * 4. OAuth client context determines workspace visibility
     * 5. Multi-tenant security is maintained
     *
     * @test
     */
    it('only returns workspaces for the current client', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];


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

    /**
     * Test that verifies email-only team members are included in workspace data.
     *
     * This test ensures that:
     * 1. Team members invited by email (without user accounts) are included
     * 2. Email-only members appear in team member lists when user has permissions
     * 3. The email field is properly included for members without user accounts
     * 4. Mixed team membership (users and email-only) works correctly
     * 5. Invitation-based team members are properly handled
     *
     * @test
     */
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


        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'email' => 'invited@example.com',
            'status' => 'active',
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

    /**
     * Test that verifies sensitive user data is excluded from API responses.
     *
     * This test ensures that:
     * 1. Password fields are never included in user data
     * 2. Only safe user attributes are exposed via the API
     * 3. Security is maintained by filtering sensitive information
     * 4. The response includes only allowed user fields
     * 5. Data leakage is prevented through proper field filtering
     *
     * @test
     */
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

    /**
     * Test that verifies the /api/user endpoint requires authentication.
     *
     * This test ensures that:
     * 1. Unauthenticated requests to /api/user are rejected
     * 2. The endpoint returns appropriate HTTP status for unauthorized access
     * 3. Authentication middleware is properly protecting the endpoint
     * 4. Security is enforced for all user data access
     *
     * @test
     */
    it('requires authentication', function () {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    });

    /**
     * Test that verifies users with no workspaces receive an empty array.
     *
     * This test ensures that:
     * 1. Users without any workspaces receive a valid response
     * 2. The workspaces field is an empty array rather than null
     * 3. The endpoint handles users with no workspace associations gracefully
     * 4. Empty states are properly handled and returned
     *
     * @test
     */
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

    /**
     * Test that verifies users with teams:view permission see all teams in workspace.
     *
     * This test ensures that:
     * 1. Users with teams:view permission can see all teams in workspace
     * 2. Team visibility is not limited to only teams the user belongs to
     * 3. The teams:view permission grants access to the complete team list
     * 4. Proper permission-based authorization for team data
     * 5. Both user's teams and other teams are visible with correct permissions
     *
     * @test
     */
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


        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();


        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);


        $team1 = Team::factory()->create(['workspace_id' => $workspace->id]);
        $team2 = Team::factory()->create(['workspace_id' => $workspace->id]);


        TeamMember::factory()->create([
            'team_id' => $team1->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);


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
        expect($teamIds)->toContain($adminTeam->id);
    });

    /**
     * Test that verifies users with teams:view permission can see teams in workspace.
     *
     * This test ensures that:
     * 1. Users with teams:view permission can access team information
     * 2. The Administrators team automatically has teams:view permission
     * 3. All teams in the workspace are visible when permission is granted
     * 4. Team names and details are properly included in the response
     * 5. Permission-based team visibility works correctly
     *
     * @test
     */
    it('allows user with teams:view permission to see teams in workspace', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);


        $team1 = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dev Team',
        ]);

        $team2 = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'QA Team',
        ]);


        $teamViewer = User::factory()->create();
        $viewerOauthData = createOAuthHeadersForClient($teamViewer, $client->id);
        $viewerHeaders = $viewerOauthData['headers'];


        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamViewer->id,
            'status' => 'active',
        ]);


        TeamMember::factory()->create([
            'team_id' => $team1->id,
            'user_id' => $teamViewer->id,
            'status' => 'active',
        ]);



        $response = $this->withHeaders($viewerHeaders)->getJson('/api/user');

        $response->assertStatus(200);


        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);
        expect($workspaces[0]['id'])->toBe($workspace->id);


        $responseTeams = $workspaces[0]['teams'];
        expect($responseTeams)->toHaveCount(3);

        $teamNames = collect($responseTeams)->pluck('name')->toArray();
        expect($teamNames)->toContain('Administrators');
        expect($teamNames)->toContain('Dev Team');
        expect($teamNames)->toContain('QA Team');
    });

    /**
     * Test that verifies users without teams:view permission cannot see teams.
     *
     * This test ensures that:
     * 1. Users without teams:view permission receive empty teams array
     * 2. Team information is properly restricted based on permissions
     * 3. Basic workspace information remains visible to team members
     * 4. Permission-based access control works for team data
     * 5. Security is maintained by hiding unauthorized team information
     *
     * @test
     */
    it('prevents user without teams:view permission from seeing teams in workspace', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);


        $team1 = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dev Team',
        ]);


        $restrictedUser = User::factory()->create();
        $restrictedOauthData = createOAuthHeadersForClient($restrictedUser, $client->id);
        $restrictedHeaders = $restrictedOauthData['headers'];


        $regularTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Regular Team',
        ]);


        $teamsViewPermission = $regularTeam->permissions()->where('slug', 'teams:view')->first();
        if ($teamsViewPermission) {
            $regularTeam->permissions()->detach($teamsViewPermission->id);
        }

        TeamMember::factory()->create([
            'team_id' => $regularTeam->id,
            'user_id' => $restrictedUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($restrictedHeaders)->getJson('/api/user');

        $response->assertStatus(200);


        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);
        expect($workspaces[0]['id'])->toBe($workspace->id);
        expect($workspaces[0]['name'])->toBe($workspace->name);


        $responseTeams = $workspaces[0]['teams'];
        expect($responseTeams)->toHaveCount(0);
    });

    /**
     * Test that verifies users with teamMembers:view permission can see team members.
     *
     * This test ensures that:
     * 1. Users with teamMembers:view permission can see team member lists
     * 2. Team member data includes user information when permission is granted
     * 3. The Administrators team has teamMembers:view permission by default
     * 4. Team member details are properly included in team data
     * 5. Permission-based team member visibility works correctly
     *
     * @test
     */
    it('allows user with teamMembers:view permission to see team members', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dev Team',
        ]);

        // Add team members to the team
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $member1->id,
            'status' => 'active',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $member2->id,
            'status' => 'active',
        ]);

        // Create a user who will have both teams:view and teamMembers:view permission
        $teamMemberViewer = User::factory()->create();
        $viewerOauthData = createOAuthHeadersForClient($teamMemberViewer, $client->id);
        $viewerHeaders = $viewerOauthData['headers'];

        // Get the Administrators team (auto-created) and add the user to it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $teamMemberViewer->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($viewerHeaders)->getJson('/api/user');

        $response->assertStatus(200);


        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);

        // Verify teams are visible - user sees teams they're members of
        $responseTeams = $workspaces[0]['teams'];
        expect($responseTeams)->toHaveCount(2); // Administrators + Dev Team

        // Find the Dev Team and verify team members are visible
        $devTeam = collect($responseTeams)->firstWhere('name', 'Dev Team');
        expect($devTeam)->not->toBeNull();

        expect($devTeam['team_members'])->toHaveCount(2); // member1 + member2

        // Verify team member details are included
        $teamMemberIds = collect($devTeam['team_members'])->pluck('user_id')->toArray();
        expect($teamMemberIds)->toContain($member1->id);
        expect($teamMemberIds)->toContain($member2->id);

    });

    /**
     * Test that verifies users without teamMembers:view permission cannot see team members.
     *
     * This test ensures that:
     * 1. Users without teamMembers:view permission receive empty team member arrays
     * 2. Team member information is properly restricted based on permissions
     * 3. Teams are still visible if user has teams:view permission
     * 4. Team member data is filtered out when permission is missing
     * 5. Permission-based access control works for sensitive team member data
     *
     * @test
     */
    it('prevents user without teamMembers:view permission from seeing team members', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        // Create a team
        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dev Team',
        ]);

        // Add team members to the team
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $member1->id,
            'status' => 'active',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $member2->id,
            'status' => 'active',
        ]);


        $restrictedUser = User::factory()->create();
        $restrictedOauthData = createOAuthHeadersForClient($restrictedUser, $client->id);
        $restrictedHeaders = $restrictedOauthData['headers'];

        // Get the Administrators team and remove teamMembers:view permission from it
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        $teamMembersViewPermission = Permission::where('client_id', $client->id)
            ->where('slug', 'teamMembers:view')
            ->first();

        $adminTeam->permissions()->detach($teamMembersViewPermission->id);

        // Add restricted user to Administrators team (now has all permissions except teamMembers:view)
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $restrictedUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($restrictedHeaders)->getJson('/api/user');

        $response->assertStatus(200);

        // Verify workspace is visible
        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);

        // Verify teams are visible (user has teams:view from modified Administrators team)
        $responseTeams = $workspaces[0]['teams'];
        expect($responseTeams)->toHaveCount(2); // Administrators + Dev Team

        // Find the Dev Team and verify team members are NOT visible (empty array)
        $devTeam = collect($responseTeams)->firstWhere('name', 'Dev Team');
        expect($devTeam)->not->toBeNull();
        expect($devTeam['team_members'])->toHaveCount(0); // No team members visible due to lack of teamMembers:view permission

        // Verify Administrators team also has no visible team members
        $adminTeam = collect($responseTeams)->firstWhere('name', 'Administrators');
        expect($adminTeam)->not->toBeNull();
        expect($adminTeam['team_members'])->toHaveCount(0); // No team members visible due to lack of teamMembers:view permission
    });

    /**
     * Test that verifies basic workspace information is always visible to team members.
     *
     * This test ensures that:
     * 1. Team members can always see basic workspace information regardless of permissions
     * 2. Workspace name, slug, dates, and IDs are always accessible
     * 3. Teams and team members may be hidden, but workspace basics remain visible
     * 4. Users can identify which workspaces they belong to
     * 5. Essential workspace context is preserved for all team members
     *
     * @test
     */
    it('always shows basic workspace info to team members regardless of permissions', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
            'name' => 'Important Workspace',
            'slug' => 'important-workspace',
        ]);


        $basicUser = User::factory()->create();
        $basicOauthData = createOAuthHeadersForClient($basicUser, $client->id);
        $basicHeaders = $basicOauthData['headers'];


        $emptyTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Empty Team',
        ]);


        $emptyTeam->permissions()->detach();

        TeamMember::factory()->create([
            'team_id' => $emptyTeam->id,
            'user_id' => $basicUser->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($basicHeaders)->getJson('/api/user');

        $response->assertStatus(200);


        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);

        $workspaceData = $workspaces[0];
        expect($workspaceData['id'])->toBe($workspace->id);
        expect($workspaceData['name'])->toBe('Important Workspace');
        expect($workspaceData['slug'])->toBe('important-workspace');
        expect($workspaceData['user_id'])->toBe($workspaceOwner->id);
        expect($workspaceData['client_id'])->toBe($client->id);
        expect($workspaceData)->toHaveKey('created_at');
        expect($workspaceData)->toHaveKey('updated_at');


        expect($workspaceData['teams'])->toHaveCount(0);
    });

    /**
     * Test that verifies team member data visibility is correctly controlled by teamMembers:view permission.
     *
     * This test ensures that:
     * 1. Users with teamMembers:view permission see complete team member data
     * 2. Team member information includes user details when permission is granted
     * 3. The Administrators team provides full access to team member data
     * 4. Member status and user relationships are properly included
     * 5. Comprehensive team member information is available with correct permissions
     *
     * @test
     */
    it('shows correct team member data based on teamMembers:view permission', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);


        $fullAccessUser = User::factory()->create();
        $fullAccessOauthData = createOAuthHeadersForClient($fullAccessUser, $client->id);
        $fullAccessHeaders = $fullAccessOauthData['headers'];


        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        TeamMember::factory()->create([
            'team_id' => $adminTeam->id,
            'user_id' => $fullAccessUser->id,
            'status' => 'active',
        ]);


        $devTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dev Team',
        ]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $devTeam->id,
            'user_id' => $member1->id,
            'status' => 'active',
        ]);

        TeamMember::factory()->create([
            'team_id' => $devTeam->id,
            'user_id' => $member2->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders($fullAccessHeaders)->getJson('/api/user');

        $response->assertStatus(200);

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);


        $teams = $workspaces[0]['teams'];
        expect($teams)->toHaveCount(2); // Administrators + Dev Team


        $devTeamData = collect($teams)->firstWhere('name', 'Dev Team');
        expect($devTeamData)->not->toBeNull();
        expect($devTeamData['team_members'])->toHaveCount(2);


        $teamMemberIds = collect($devTeamData['team_members'])->pluck('user_id')->toArray();
        expect($teamMemberIds)->toContain($member1->id);
        expect($teamMemberIds)->toContain($member2->id);


        $member1Data = collect($devTeamData['team_members'])->firstWhere('user_id', $member1->id);
        expect($member1Data['user'])->not->toBeNull();
        expect($member1Data['user']['email'])->toBe($member1->email);
    });

    /**
     * Test that verifies mixed permission scenarios work correctly.
     *
     * This test ensures that:
     * 1. Users with only teams:view permission see teams but not team members
     * 2. Partial permissions are correctly enforced
     * 3. Team member arrays are empty when teamMembers:view permission is missing
     * 4. Mixed permission combinations work as expected
     * 5. Fine-grained permission control functions properly
     *
     * @test
     */
    it('handles workspace with mixed permission scenarios correctly', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);


        $teamsOnlyUser = User::factory()->create();
        $teamsOnlyOauthData = createOAuthHeadersForClient($teamsOnlyUser, $client->id);
        $teamsOnlyHeaders = $teamsOnlyOauthData['headers'];


        $viewOnlyTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'View Only Team',
        ]);


        $teamsViewPermission = Permission::where('client_id', $client->id)
            ->where('slug', 'teams:view')
            ->first();

        $viewOnlyTeam->permissions()->attach($teamsViewPermission->id);


        TeamMember::factory()->create([
            'team_id' => $viewOnlyTeam->id,
            'user_id' => $teamsOnlyUser->id,
            'status' => 'active',
        ]);


        $team1 = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Team 1']);
        $team2 = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Team 2']);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        TeamMember::factory()->create(['team_id' => $team1->id, 'user_id' => $member1->id, 'status' => 'active']);
        TeamMember::factory()->create(['team_id' => $team2->id, 'user_id' => $member2->id, 'status' => 'active']);

        $response = $this->withHeaders($teamsOnlyHeaders)->getJson('/api/user');

        $response->assertStatus(200);

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);


        expect($workspaces[0]['name'])->toBe($workspace->name);
        expect($workspaces[0]['id'])->toBe($workspace->id);


        $teams = $workspaces[0]['teams'];
        expect($teams)->toHaveCount(4); // Administrators + View Only Team + Team 1 + Team 2


        foreach ($teams as $team) {
            expect($team['team_members'])->toHaveCount(0);
        }
    });

    /**
     * Test that verifies workspace data filtering for users with no permissions.
     *
     * This test ensures that:
     * 1. Users with no permissions still see basic workspace information
     * 2. Teams are completely hidden when teams:view permission is missing
     * 3. Essential workspace context remains available for identification
     * 4. Zero-permission scenarios are handled gracefully
     * 5. Security restrictions work correctly while maintaining usability
     *
     * @test
     */
    it('correctly filters workspace data for users with no permissions', function () {
        // Create workspace owner
        $workspaceOwner = User::factory()->create();
        $oauthData = createOAuthHeadersForClient($workspaceOwner);
        $client = $oauthData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
            'name' => 'Restricted Workspace',
            'slug' => 'restricted-workspace',
        ]);


        $restrictedUser = User::factory()->create();
        $restrictedOauthData = createOAuthHeadersForClient($restrictedUser, $client->id);
        $restrictedHeaders = $restrictedOauthData['headers'];


        $emptyTeam = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Empty Permissions Team',
        ]);


        $emptyTeam->permissions()->detach();


        TeamMember::factory()->create([
            'team_id' => $emptyTeam->id,
            'user_id' => $restrictedUser->id,
            'status' => 'active',
        ]);


        $team1 = Team::factory()->create(['workspace_id' => $workspace->id]);
        $team2 = Team::factory()->create(['workspace_id' => $workspace->id]);

        $otherUser1 = User::factory()->create();
        $otherUser2 = User::factory()->create();

        TeamMember::factory()->create(['team_id' => $team1->id, 'user_id' => $otherUser1->id, 'status' => 'active']);
        TeamMember::factory()->create(['team_id' => $team2->id, 'user_id' => $otherUser2->id, 'status' => 'active']);

        $response = $this->withHeaders($restrictedHeaders)->getJson('/api/user');

        $response->assertStatus(200);

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);

        $workspaceData = $workspaces[0];


        expect($workspaceData['id'])->toBe($workspace->id);
        expect($workspaceData['name'])->toBe('Restricted Workspace');
        expect($workspaceData['slug'])->toBe('restricted-workspace');
        expect($workspaceData['user_id'])->toBe($workspaceOwner->id);
        expect($workspaceData['client_id'])->toBe($client->id);
        expect($workspaceData)->toHaveKey('created_at');
        expect($workspaceData)->toHaveKey('updated_at');


        expect($workspaceData['teams'])->toHaveCount(0);
        expect($workspaceData['teams'])->toBeArray();
    });

    /**
     * Test that verifies pending team memberships are excluded from user workspaces.
     *
     * This test ensures that:
     * 1. Users with pending team invitations do not see those workspaces
     * 2. Only active team memberships grant access to workspaces
     * 3. Pending invitations are properly filtered out
     * 4. Workspace visibility is based on active membership status
     * 5. Security is maintained by hiding workspaces for pending invitations
     *
     * @test
     */
    it('excludes workspaces where user has only pending team memberships', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'workspaces' => [],
                ]
            ]);

        expect($response->json('data.workspaces'))->toHaveCount(0);
    });

    /**
     * Test that verifies pending team members are excluded from team member lists.
     *
     * This test ensures that:
     * 1. Pending team members are not included in team member lists
     * 2. Only active team members are visible when user has teamMembers:view permission
     * 3. Team member filtering works correctly in workspace responses
     * 4. Pending invitations remain hidden from team member views
     * 5. Active and pending memberships are properly distinguished
     *
     * @test
     */
    it('excludes pending team members from team member lists', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Test Team',
        ]);

        $activeUser = User::factory()->create();
        $pendingUser = User::factory()->create();

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $activeUser->id,
            'status' => 'active',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $pendingUser->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);

        $teams = $workspaces[0]['teams'];
        $testTeam = collect($teams)->firstWhere('name', 'Test Team');
        expect($testTeam)->not->toBeNull();

        expect($testTeam['team_members'])->toHaveCount(1);
        expect($testTeam['team_members'][0]['user_id'])->toBe($activeUser->id);
        expect($testTeam['team_members'][0]['status'])->toBe('active');
    });

    /**
     * Test that verifies pending invitations are included in user response.
     *
     * This test ensures that:
     * 1. Users can see their pending team invitations in the API response
     * 2. Pending invitations include workspace and team information
     * 3. Only invitations for the current OAuth client are included
     * 4. Invitation data includes all necessary details for decision making
     * 5. Multiple pending invitations are properly listed
     *
     * @test
     */
    it('includes pending invitations in user response', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspaceOwner1 = User::factory()->create();
        $workspace1 = Workspace::factory()->create([
            'user_id' => $workspaceOwner1->id,
            'client_id' => $client->id,
            'name' => 'Design Team Workspace',
            'slug' => 'design-team',
        ]);

        $workspaceOwner2 = User::factory()->create();
        $workspace2 = Workspace::factory()->create([
            'user_id' => $workspaceOwner2->id,
            'client_id' => $client->id,
            'name' => 'Marketing Workspace',
            'slug' => 'marketing',
        ]);

        $team1 = Team::factory()->create([
            'workspace_id' => $workspace1->id,
            'name' => 'UI/UX Team',
        ]);

        $team2 = Team::factory()->create([
            'workspace_id' => $workspace2->id,
            'name' => 'Content Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team1->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team2->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'workspaces' => [],
                    'pending_invitations' => [
                        [
                            'team_name' => 'UI/UX Team',
                            'workspace' => [
                                'id' => $workspace1->id,
                                'name' => 'Design Team Workspace',
                                'slug' => 'design-team',
                            ],
                            'status' => 'pending',
                        ],
                        [
                            'team_name' => 'Content Team',
                            'workspace' => [
                                'id' => $workspace2->id,
                                'name' => 'Marketing Workspace',
                                'slug' => 'marketing',
                            ],
                            'status' => 'pending',
                        ],
                    ],
                ]
            ]);

        $pendingInvitations = $response->json('data.pending_invitations');
        expect($pendingInvitations)->toHaveCount(2);

        foreach ($pendingInvitations as $invitation) {
            expect($invitation)->toHaveKey('invitation_id');
            expect($invitation)->toHaveKey('team_id');
            expect($invitation)->toHaveKey('team_name');
            expect($invitation)->toHaveKey('workspace');
            expect($invitation)->toHaveKey('invited_at');
            expect($invitation)->toHaveKey('status');
            expect($invitation['status'])->toBe('pending');
        }
    });

    /**
     * Test that verifies pending invitations are filtered by OAuth client.
     *
     * This test ensures that:
     * 1. Only pending invitations for the current OAuth client are shown
     * 2. Invitations from other clients are excluded
     * 3. Client isolation is maintained for pending invitations
     * 4. Multi-tenant security is preserved
     * 5. Users only see relevant invitations for their current context
     *
     * @test
     */
    it('filters pending invitations by OAuth client', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspaceCurrentClient = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $client->id,
        ]);

        $otherClient = \App\Models\Client::factory()->create();
        $workspaceOtherClient = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $otherClient->id,
        ]);

        $teamCurrentClient = Team::factory()->create([
            'workspace_id' => $workspaceCurrentClient->id,
            'name' => 'Current Client Team',
        ]);

        $teamOtherClient = Team::factory()->create([
            'workspace_id' => $workspaceOtherClient->id,
            'name' => 'Other Client Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $teamCurrentClient->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        TeamMember::factory()->create([
            'team_id' => $teamOtherClient->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $pendingInvitations = $response->json('data.pending_invitations');
        expect($pendingInvitations)->toHaveCount(1);
        expect($pendingInvitations[0]['team_name'])->toBe('Current Client Team');
        expect($pendingInvitations[0]['workspace']['id'])->toBe($workspaceCurrentClient->id);
    });

    /**
     * Test that verifies active memberships are not included in pending invitations.
     *
     * This test ensures that:
     * 1. Active team memberships are excluded from pending invitations
     * 2. Only truly pending invitations are listed
     * 3. Status filtering works correctly
     * 4. The distinction between active and pending is maintained
     * 5. Users don't see duplicate information
     *
     * @test
     */
    it('excludes active memberships from pending invitations', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $client->id,
        ]);

        $team1 = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Active Team',
        ]);

        $team2 = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Pending Team',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team1->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        TeamMember::factory()->create([
            'team_id' => $team2->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $pendingInvitations = $response->json('data.pending_invitations');
        expect($pendingInvitations)->toHaveCount(1);
        expect($pendingInvitations[0]['team_name'])->toBe('Pending Team');

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(1);
    });

    /**
     * Test that verifies empty pending invitations array when user has no pending invitations.
     *
     * This test ensures that:
     * 1. Users with no pending invitations receive an empty array
     * 2. The pending_invitations field is always present in the response
     * 3. Empty states are handled gracefully
     * 4. The response structure is consistent
     * 5. No errors occur when there are no pending invitations
     *
     * @test
     */
    it('returns empty array when user has no pending invitations', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'workspaces' => [],
                    'pending_invitations' => [],
                ]
            ]);

        expect($response->json('data.pending_invitations'))->toBeArray();
        expect($response->json('data.pending_invitations'))->toHaveCount(0);
    });

    /**
     * Test that verifies client permissions are included in user response.
     *
     * This test ensures that:
     * 1. All permissions for the current OAuth client are included
     * 2. Permissions are returned with complete details
     * 3. Permissions are ordered by name for consistency
     * 4. Only permissions for the current client are included
     * 5. The client_permissions field is always present
     *
     * @test
     */
    it('includes client permissions in user response', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'workspaces',
                    'pending_invitations',
                    'client_permissions' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'created_at',
                            'updated_at',
                        ]
                    ]
                ]
            ]);

        $clientPermissions = $response->json('data.client_permissions');
        expect($clientPermissions)->toBeArray();
        expect($clientPermissions)->toHaveCount(8); // Default permissions count

        // Verify specific permission details
        $permissionSlugs = collect($clientPermissions)->pluck('slug')->toArray();
        $expectedSlugs = [
            'teams:create',
            'teams:update',
            'teams:delete',
            'teams:view',
            'teamMembers:create',
            'teamMembers:update',
            'teamMembers:delete',
            'teamMembers:view'
        ];

        foreach ($expectedSlugs as $expectedSlug) {
            expect($permissionSlugs)->toContain($expectedSlug);
        }

        // Verify permissions are ordered by name
        $permissionNames = collect($clientPermissions)->pluck('name')->toArray();
        $sortedNames = collect($clientPermissions)->sortBy('name')->pluck('name')->toArray();
        expect($permissionNames)->toEqual($sortedNames);
    });

    /**
     * Test that verifies client permissions are filtered by OAuth client.
     *
     * This test ensures that:
     * 1. Only permissions for the current OAuth client are returned
     * 2. Permissions from other clients are excluded
     * 3. Client isolation is maintained for permission data
     * 4. Multi-tenant security is preserved
     * 5. Users only see permissions relevant to their current context
     *
     * @test
     */
    it('filters client permissions by OAuth client', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        // Create another client with different permissions
        $otherClient = \App\Models\Client::factory()->create();
        \App\Models\Permission::factory()->create([
            'client_id' => $otherClient->id,
            'name' => 'Other Client Permission',
            'slug' => 'other:permission',
        ]);

        // Create a custom permission for current client
        $customPermission = \App\Models\Permission::factory()->create([
            'client_id' => $client->id,
            'name' => 'Custom Permission',
            'slug' => 'custom:permission',
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200);

        $clientPermissions = $response->json('data.client_permissions');
        expect($clientPermissions)->toHaveCount(9); // 8 default + 1 custom

        $permissionSlugs = collect($clientPermissions)->pluck('slug')->toArray();
        expect($permissionSlugs)->toContain('custom:permission');
        expect($permissionSlugs)->not->toContain('other:permission');

        // Verify all permissions belong to current client
        foreach ($clientPermissions as $permission) {
            $dbPermission = \App\Models\Permission::find($permission['id']);
            expect($dbPermission->client_id)->toBe($client->id);
        }
    });

    /**
     * Test that verifies client permissions field is present even with no custom permissions.
     *
     * This test ensures that:
     * 1. Client permissions field is always present in response
     * 2. Default permissions are included for new clients
     * 3. The response structure is consistent
     * 4. Empty custom permissions don't break the response
     * 5. Default permissions are properly created for clients
     *
     * @test
     */
    it('includes default client permissions for new clients', function () {
        // Create a fresh user with new OAuth client
        $newUser = \App\Models\User::factory()->create();
        $authData = setupUserTestAuth($newUser);
        $headers = $authData['headers'];

        $response = $this->withHeaders($headers)->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $newUser->id,
                    'email' => $newUser->email,
                ]
            ]);

        $clientPermissions = $response->json('data.client_permissions');
        expect($clientPermissions)->toBeArray();
        expect($clientPermissions)->toHaveCount(8); // Default permissions

        // Verify all default permissions are present
        $permissionSlugs = collect($clientPermissions)->pluck('slug')->toArray();
        $defaultSlugs = [
            'teams:create', 'teams:update', 'teams:delete', 'teams:view',
            'teamMembers:create', 'teamMembers:update', 'teamMembers:delete', 'teamMembers:view'
        ];

        foreach ($defaultSlugs as $slug) {
            expect($permissionSlugs)->toContain($slug);
        }
    });

    /**
     * Test that verifies pending invitations are removed after acceptance.
     *
     * This test ensures that:
     * 1. Pending invitations appear in the user response initially
     * 2. After accepting an invitation, it's removed from pending_invitations
     * 3. The workspace becomes accessible after invitation acceptance
     * 4. The invitation acceptance flow works end-to-end
     * 5. User data is properly updated after invitation status changes
     *
     * @test
     */
    it('removes pending invitation after acceptance and grants workspace access', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
            'name' => 'Development Workspace',
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Backend Team',
        ]);

        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Check initial state - pending invitation should be present
        $response = $this->withHeaders($headers)->getJson('/api/user');
        $response->assertStatus(200);

        $pendingInvitations = $response->json('data.pending_invitations');
        expect($pendingInvitations)->toHaveCount(1);
        expect($pendingInvitations[0]['team_name'])->toBe('Backend Team');
        expect($pendingInvitations[0]['workspace']['name'])->toBe('Development Workspace');

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(0); // No workspace access yet

        // Accept the invitation
        $acceptResponse = $this->withHeaders($headers)
            ->patchJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/accept");

        $acceptResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Team invitation accepted successfully',
                'data' => [
                    'status' => 'active',
                ],
            ]);

        // Check state after acceptance - invitation should be gone, workspace should be accessible
        $responseAfter = $this->withHeaders($headers)->getJson('/api/user');
        $responseAfter->assertStatus(200);

        $pendingInvitationsAfter = $responseAfter->json('data.pending_invitations');
        expect($pendingInvitationsAfter)->toHaveCount(0); // Invitation should be gone

        $workspacesAfter = $responseAfter->json('data.workspaces');
        expect($workspacesAfter)->toHaveCount(1); // Workspace should now be accessible
        expect($workspacesAfter[0]['name'])->toBe('Development Workspace');
    });

    /**
     * Test that verifies pending invitations are removed after rejection.
     *
     * This test ensures that:
     * 1. Pending invitations appear in the user response initially
     * 2. After rejecting an invitation, it's removed from pending_invitations
     * 3. The workspace remains inaccessible after invitation rejection
     * 4. The invitation rejection flow works end-to-end
     * 5. User data is properly updated after invitation rejection
     *
     * @test
     */
    it('removes pending invitation after rejection and maintains no workspace access', function () {
        $authData = setupUserTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspaceOwner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $workspaceOwner->id,
            'client_id' => $client->id,
            'name' => 'Marketing Workspace',
        ]);

        $team = Team::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Content Team',
        ]);

        $teamMember = TeamMember::factory()->create([
            'team_id' => $team->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Check initial state - pending invitation should be present
        $response = $this->withHeaders($headers)->getJson('/api/user');
        $response->assertStatus(200);

        $pendingInvitations = $response->json('data.pending_invitations');
        expect($pendingInvitations)->toHaveCount(1);
        expect($pendingInvitations[0]['team_name'])->toBe('Content Team');

        $workspaces = $response->json('data.workspaces');
        expect($workspaces)->toHaveCount(0); // No workspace access initially

        // Reject the invitation
        $rejectResponse = $this->withHeaders($headers)
            ->deleteJson("/api/workspaces/{$workspace->id}/teams/{$team->id}/members/{$teamMember->id}/reject");

        $rejectResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Team invitation rejected successfully',
            ]);

        // Check state after rejection - invitation should be gone, workspace should remain inaccessible
        $responseAfter = $this->withHeaders($headers)->getJson('/api/user');
        $responseAfter->assertStatus(200);

        $pendingInvitationsAfter = $responseAfter->json('data.pending_invitations');
        expect($pendingInvitationsAfter)->toHaveCount(0); // Invitation should be gone

        $workspacesAfter = $responseAfter->json('data.workspaces');
        expect($workspacesAfter)->toHaveCount(0); // Workspace should remain inaccessible
    });
});
