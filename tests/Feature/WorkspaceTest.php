<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\Client;
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

    it('requires authentication', function () {
        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
        ]);

        $response->assertStatus(401);
    });

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
});
