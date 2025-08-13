<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\Client;
use App\Models\Permission;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// Helper function to setup OAuth and workspace for workspace tests
function setupWorkspaceTestAuth($user): array {
    $oauthData = createOAuthHeadersForClient($user);
    $headers = $oauthData['headers'];
    $client = $oauthData['client'];

    return compact('headers', 'client', 'user');
}

describe('Workspace API', function () {
    /**
     * Test that verifies authenticated users can create workspaces.
     *
     * This test ensures that:
     * 1. Authenticated users can create new workspaces via POST request
     * 2. Workspace creation request is processed successfully
     * 3. The workspace is properly stored in the database with correct attributes
     * 4. Auto-generated slug is created from workspace name
     * 5. The response includes complete workspace data with proper structure
     *
     * @test
     */
    it('can create a workspace with authentication', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
        ], $headers);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'user_id',
                    'client_id',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Engineering Team',
                    'slug' => 'engineering-team',
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                ],
                'message' => 'Workspace created successfully',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'engineering-team',
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);
    });

    /**
     * Test that verifies workspaces can be created with custom slugs.
     *
     * This test ensures that:
     * 1. Users can specify custom slugs during workspace creation
     * 2. Custom slugs are preserved instead of auto-generating from name
     * 3. The workspace creation process respects provided slug values
     * 4. Manual slug control is available when needed
     * 5. Custom slug validation and storage works correctly
     *
     * @test
     */
    it('can create a workspace with custom slug', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'eng-team',
        ], $headers);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Engineering Team',
                    'slug' => 'eng-team',
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                ],
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'eng-team',
            'user_id' => $this->user->id,
            'client_id' => $client->id,
        ]);
    });

    /**
     * Test that verifies unique slug generation when conflicts occur within the same client.
     *
     * This test ensures that:
     * 1. Slug conflicts are automatically resolved for the same client
     * 2. The system appends numbers to create unique slugs
     * 3. Slug uniqueness is enforced within client scope
     * 4. Multiple workspaces with similar names can coexist
     * 5. Automatic conflict resolution works correctly
     *
     * @test
     */
    it('generates unique slug when slug is already taken for same client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        Workspace::factory()->forUser($this->user)->forClient($client->id)->create([
            'name' => 'Engineering',
            'slug' => 'eng',
        ]);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Department',
            'slug' => 'eng',
        ], $headers);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Engineering Department',
                    'slug' => 'eng-2',
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                ],
            ]);

        $this->assertDatabaseHas('workspaces', [
            'slug' => 'eng',
            'client_id' => $client->id,
        ]);

        $this->assertDatabaseHas('workspaces', [
            'slug' => 'eng-2',
            'name' => 'Engineering Department',
            'client_id' => $client->id,
        ]);
    });

    /**
     * Test that verifies same slugs are allowed for different clients.
     *
     * This test ensures that:
     * 1. Different clients can have workspaces with identical slugs
     * 2. Slug uniqueness is scoped to individual clients, not globally
     * 3. Client isolation is maintained for workspace slugs
     * 4. Multi-tenant slug management works correctly
     * 5. Each client has independent workspace naming
     *
     * @test
     */
    it('allows same slug for different clients', function () {
        // Setup first client and workspace
        $authData1 = setupWorkspaceTestAuth($this->user);
        $client1 = $authData1['client'];

        Workspace::factory()->forUser($this->user)->forClient($client1->id)->create([
            'name' => 'Engineering',
            'slug' => 'eng',
        ]);

        // Setup second client with different OAuth
        $authData2 = setupWorkspaceTestAuth($this->user);
        $headers2 = $authData2['headers'];
        $client2 = $authData2['client'];

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering',
            'slug' => 'eng',
        ], $headers2);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slug' => 'eng',
                    'client_id' => $client2->id,
                ],
            ]);
    });

    it('generates unique slug when no slug provided and name conflicts within client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        Workspace::factory()->forUser($this->user)->forClient($client->id)->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering',
        ], $headers);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Engineering',
                    'slug' => 'engineering-2',
                    'user_id' => $this->user->id,
                    'client_id' => $authData['client']->id,
                ],
            ]);
    });

    it('handles multiple slug conflicts correctly within client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        Workspace::factory()->forUser($this->user)->forClient($client->id)->create(['slug' => 'test']);
        Workspace::factory()->forUser($this->user)->forClient($client->id)->create(['slug' => 'test-2']);
        Workspace::factory()->forUser($this->user)->forClient($client->id)->create(['slug' => 'test-3']);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Test Workspace',
            'slug' => 'test',
        ], $headers);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slug' => 'test-4',
                    'client_id' => $client->id,
                ],
            ]);
    });

    /**
     * Test that verifies workspace endpoints require authentication.
     *
     * This test ensures that:
     * 1. Unauthenticated requests to workspace endpoints are rejected
     * 2. Appropriate HTTP status codes are returned for unauthorized access
     * 3. Authentication middleware is properly protecting workspace endpoints
     * 4. Security is enforced for all workspace operations
     *
     * @test
     */
    it('requires authentication', function () {
        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
        ]);

        $response->assertStatus(401);
    });

    /**
     * Test that verifies workspace creation validates required fields.
     *
     * This test ensures that:
     * 1. Required fields are properly validated during workspace creation
     * 2. Missing required data results in validation errors
     * 3. Appropriate error messages are returned for validation failures
     * 4. Data integrity is maintained through proper validation
     *
     * @test
     */
    it('validates required fields', function () {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    });

    it('validates field lengths', function () {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    });

    it('validates field formats', function () {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'invalid slug with spaces',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    });

    it('only lists workspaces for current client', function () {
        // Setup first client and create workspace
        $authData1 = setupWorkspaceTestAuth($this->user);
        $headers1 = $authData1['headers'];
        $client1 = $authData1['client'];

        $workspace1 = Workspace::factory()->forUser($this->user)->forClient($client1->id)->create(['name' => 'Client 1 Workspace']);

        // Setup second client and create workspace
        $authData2 = setupWorkspaceTestAuth($this->user);
        $client2 = $authData2['client'];

        $workspace2 = Workspace::factory()->forUser($this->user)->forClient($client2->id)->create(['name' => 'Client 2 Workspace']);

        // Test that listing workspaces only returns workspaces for current client
        $response = $this->getJson('/api/workspaces', $headers1);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $workspaceIds = collect($response->json('data'))->pluck('id');
        expect($workspaceIds)->toContain($workspace1->id);
        expect($workspaceIds)->not->toContain($workspace2->id);
    });

    /**
     * Test that verifies users can retrieve specific workspace details.
     *
     * This test ensures that:
     * 1. Users can access individual workspace details via GET request
     * 2. Workspace data is properly returned with complete information
     * 3. The response includes workspace attributes and relationships
     * 4. Individual workspace access works correctly
     * 5. Workspace owners can view their workspace details
     *
     * @test
     */
    it('can show a specific workspace for current client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client->id)->create([
            'name' => 'My Workspace',
            'slug' => 'my-workspace',
        ]);

        $response = $this->getJson("/api/workspaces/{$workspace->id}", $headers);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $workspace->id,
                    'name' => 'My Workspace',
                    'slug' => 'my-workspace',
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                ],
            ]);
    });

    it('cannot show workspace belonging to different client', function () {
        // Setup first client with auth
        $authData1 = setupWorkspaceTestAuth($this->user);
        $headers1 = $authData1['headers'];
        $client1 = $authData1['client'];

        // Setup second client and create workspace there
        $authData2 = setupWorkspaceTestAuth($this->user);
        $client2 = $authData2['client'];

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client2->id)->create();

        // Try to access workspace from different client using first client's auth
        $response = $this->getJson("/api/workspaces/{$workspace->id}", $headers1);

        $response->assertStatus(404);
    });

    it('cannot show workspace belonging to another user', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->forUser($otherUser)->forClient($client->id)->create();

        $response = $this->getJson("/api/workspaces/{$workspace->id}", $headers);

        $response->assertStatus(403);
    });

    /**
     * Test that verifies workspace owners can update their workspaces.
     *
     * This test ensures that:
     * 1. Workspace owners can modify workspace details via PUT request
     * 2. Workspace updates are processed successfully
     * 3. Updated data is properly saved to the database
     * 4. The response confirms the successful update
     * 5. Workspace modification permissions work correctly
     *
     * @test
     */
    it('can update a workspace for current client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client->id)->create([
            'name' => 'Old Name',
            'slug' => 'old-slug',
        ]);

        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'New Name',
            'slug' => 'new-slug',
        ], $headers);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $workspace->id,
                    'name' => 'New Name',
                    'slug' => 'new-slug',
                    'user_id' => $this->user->id,
                    'client_id' => $client->id,
                ],
                'message' => 'Workspace updated successfully',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'New Name',
            'slug' => 'new-slug',
            'client_id' => $client->id,
        ]);
    });

    it('generates unique slug when updating with conflicting slug within client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace1 = Workspace::factory()->forUser($this->user)->forClient($client->id)->create(['slug' => 'existing']);
        $workspace2 = Workspace::factory()->forUser($this->user)->forClient($client->id)->create(['name' => 'Second']);

        $response = $this->putJson("/api/workspaces/{$workspace2->id}", [
            'slug' => 'existing',
        ], $headers);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'existing-2',
                    'client_id' => $client->id,
                ],
            ]);
    });

    it('cannot update workspace belonging to different client', function () {
        // Setup first client with auth
        $authData1 = setupWorkspaceTestAuth($this->user);
        $headers1 = $authData1['headers'];
        $client1 = $authData1['client'];

        // Setup second client and create workspace there
        $authData2 = setupWorkspaceTestAuth($this->user);
        $client2 = $authData2['client'];

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client2->id)->create();

        // Try to update workspace from different client using first client's auth
        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'New Name',
        ], $headers1);

        $response->assertStatus(404);
    });

    it('cannot update workspace belonging to another user', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->forUser($otherUser)->forClient($client->id)->create();

        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'New Name',
        ], $headers);

        $response->assertStatus(403);
    });

    /**
     * Test that verifies workspace owners can delete their workspaces.
     *
     * This test ensures that:
     * 1. Workspace owners can delete their workspaces via DELETE request
     * 2. Workspace deletion is processed successfully
     * 3. The workspace is completely removed from the database
     * 4. Appropriate success messages are returned
     * 5. Workspace deletion permissions work correctly
     *
     * @test
     */
    it('can delete a workspace for current client', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client->id)->create();

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}", [], $headers);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Workspace deleted successfully',
            ]);

        $this->assertDatabaseMissing('workspaces', [
            'id' => $workspace->id,
        ]);
    });

    it('cannot delete workspace belonging to different client', function () {
        // Setup first client with auth
        $authData1 = setupWorkspaceTestAuth($this->user);
        $headers1 = $authData1['headers'];
        $client1 = $authData1['client'];

        // Setup second client and create workspace there
        $authData2 = setupWorkspaceTestAuth($this->user);
        $client2 = $authData2['client'];

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client2->id)->create();

        // Try to delete workspace from different client using first client's auth
        $response = $this->deleteJson("/api/workspaces/{$workspace->id}", [], $headers1);

        $response->assertStatus(404);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    });

    it('cannot delete workspace belonging to another user', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->forUser($otherUser)->forClient($client->id)->create();

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}", [], $headers);

        $response->assertStatus(403);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    });



    it('requires authentication for all workspace endpoints', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $client = $authData['client'];
        $workspace = Workspace::factory()->forClient($client->id)->create();

        $this->getJson('/api/workspaces')->assertStatus(401);
        $this->postJson('/api/workspaces')->assertStatus(401);
        $this->getJson("/api/workspaces/{$workspace->id}")->assertStatus(401);
        $this->putJson("/api/workspaces/{$workspace->id}")->assertStatus(401);
        $this->deleteJson("/api/workspaces/{$workspace->id}")->assertStatus(401);
    });
});

describe('Workspace Model', function () {
    it('generates unique slug on creation within client scope', function () {
        $user = User::factory()->create();
        $client = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => 'test-secret',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $workspace1 = Workspace::create([
            'name' => 'Engineering',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $workspace2 = Workspace::create([
            'name' => 'Engineering',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        expect($workspace1->slug)->toBe('engineering');
        expect($workspace2->slug)->toBe('engineering-2');
    });

    it('allows same slug for different clients', function () {
        $user = User::factory()->create();
        $client1 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Client 1',
            'secret' => 'secret-1',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Client 2',
            'secret' => 'secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $workspace1 = Workspace::create([
            'name' => 'Engineering',
            'user_id' => $user->id,
            'client_id' => $client1->id,
        ]);

        $workspace2 = Workspace::create([
            'name' => 'Engineering',
            'user_id' => $user->id,
            'client_id' => $client2->id,
        ]);

        expect($workspace1->slug)->toBe('engineering');
        expect($workspace2->slug)->toBe('engineering');
    });

    it('uses provided slug if available', function () {
        $user = User::factory()->create();
        $client = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => 'test-secret',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $workspace = Workspace::create([
            'name' => 'Engineering Team',
            'slug' => 'custom-slug',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        expect($workspace->slug)->toBe('custom-slug');
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $client = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => 'test-secret',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $workspace = Workspace::factory()->forUser($user)->forClient($client->id)->create();

        expect($workspace->user)->toBeInstanceOf(User::class);
        expect($workspace->user->id)->toBe($user->id);
    });

    it('belongs to a client', function () {
        $client = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => 'test-secret',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $workspace = Workspace::factory()->forClient($client->id)->create();

        expect($workspace->client_id)->toBe($client->id);
    });

    it('can be accessed through user relationship filtered by client', function () {
        $user = User::factory()->create();

        $client1 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Client 1',
            'secret' => 'secret-1',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Client 2',
            'secret' => 'secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $workspace1 = Workspace::factory()->forUser($user)->forClient($client1->id)->create();
        $workspace2 = Workspace::factory()->forUser($user)->forClient($client2->id)->create();

        $client1Workspaces = $user->workspaces()->where('client_id', $client1->id)->get();
        $client2Workspaces = $user->workspaces()->where('client_id', $client2->id)->get();

        expect($client1Workspaces)->toHaveCount(1);
        expect($client2Workspaces)->toHaveCount(1);
        expect($client1Workspaces->first()->id)->toBe($workspace1->id);
        expect($client2Workspaces->first()->id)->toBe($workspace2->id);
    });

    it('automatically creates Administrators team when workspace is created', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Test Workspace',
        ], $headers);

        $response->assertStatus(201);
        $workspaceId = $response->json('data.id');

        // Assert that Administrators team was created
        $this->assertDatabaseHas('teams', [
            'name' => 'Administrators',
            'workspace_id' => $workspaceId,
            'description' => 'Default administrators team with full permissions',
        ]);

        // Verify the team exists through relationship
        $workspace = Workspace::find($workspaceId);
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        expect($adminTeam)->not->toBeNull();
        expect($adminTeam->name)->toBe('Administrators');
        expect($adminTeam->slug)->toBe('administrators');
    });

    it('automatically attaches all client permissions to Administrators team', function () {
        $authData = setupWorkspaceTestAuth($this->user);
        $headers = $authData['headers'];
        $client = $authData['client'];

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Test Workspace',
        ], $headers);

        $response->assertStatus(201);
        $workspaceId = $response->json('data.id');

        // Get the Administrators team
        $workspace = Workspace::find($workspaceId);
        $adminTeam = $workspace->teams()->where('name', 'Administrators')->first();
        expect($adminTeam)->not->toBeNull();

        // Assert that all client permissions are attached to the team
        $clientPermissions = Permission::where('client_id', $client->id)->get();
        $teamPermissions = $adminTeam->permissions;

        expect($teamPermissions)->toHaveCount(8); // 8 default permissions
        expect($teamPermissions->count())->toBe($clientPermissions->count());

        // Check that all default permission slugs are present
        $teamPermissionSlugs = $teamPermissions->pluck('slug')->toArray();
        $expectedSlugs = [
            'teams:create', 'teams:update', 'teams:delete', 'teams:view',
            'teamMembers:create', 'teamMembers:update', 'teamMembers:delete', 'teamMembers:view'
        ];

        foreach ($expectedSlugs as $expectedSlug) {
            expect($teamPermissionSlugs)->toContain($expectedSlug);
        }
    });
});
