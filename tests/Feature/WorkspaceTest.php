<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create a real client for testing
    $this->client = Client::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'name' => 'Test Client',
        'secret' => 'test-secret',
        'redirect_uris' => 'http://localhost',
        'grant_types' => 'authorization_code',
        'revoked' => false,
    ]);

    $this->clientId = $this->client->id;

    // Mock the getClientId method to return our test client ID
    $this->partialMock(\App\Http\Controllers\WorkspaceController::class, function ($mock) {
        $mock->shouldReceive('getClientId')
             ->andReturn($this->clientId);
    });
});

describe('Workspace API', function () {
    it('can create a workspace with authentication', function () {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
        ]);

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
                    'client_id' => $this->clientId,
                ],
                'message' => 'Workspace created successfully',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'engineering-team',
            'user_id' => $this->user->id,
            'client_id' => $this->clientId,
        ]);
    });

    it('can create a workspace with custom slug', function () {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'eng-team',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Engineering Team',
                    'slug' => 'eng-team',
                    'user_id' => $this->user->id,
                    'client_id' => $this->clientId,
                ],
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Engineering Team',
            'slug' => 'eng-team',
            'user_id' => $this->user->id,
            'client_id' => $this->clientId,
        ]);
    });

    it('generates unique slug when slug is already taken for same client', function () {
        Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create([
            'name' => 'Engineering',
            'slug' => 'eng',
        ]);

        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering Department',
            'slug' => 'eng',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Engineering Department',
                    'slug' => 'eng-2',
                    'user_id' => $this->user->id,
                    'client_id' => $this->clientId,
                ],
            ]);

        $this->assertDatabaseHas('workspaces', [
            'slug' => 'eng',
            'client_id' => $this->clientId,
        ]);

        $this->assertDatabaseHas('workspaces', [
            'slug' => 'eng-2',
            'name' => 'Engineering Department',
            'client_id' => $this->clientId,
        ]);
    });

    it('allows same slug for different clients', function () {
        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client 2',
            'secret' => 'test-secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $client2Id = $client2->id;

        Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create([
            'name' => 'Engineering',
            'slug' => 'eng',
        ]);

        // Mock controller to return second client ID
        $this->partialMock(\App\Http\Controllers\WorkspaceController::class, function ($mock) use ($client2Id) {
            $mock->shouldReceive('getClientId')->andReturn($client2Id);
        });

        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering',
            'slug' => 'eng',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slug' => 'eng',
                    'client_id' => $client2Id,
                ],
            ]);
    });

    it('generates unique slug when no slug provided and name conflicts within client', function () {
        Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);

        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Engineering',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Engineering',
                    'slug' => 'engineering-2',
                    'user_id' => $this->user->id,
                    'client_id' => $this->clientId,
                ],
            ]);
    });

    it('handles multiple slug conflicts correctly within client', function () {
        Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create(['slug' => 'test']);
        Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create(['slug' => 'test-2']);
        Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create(['slug' => 'test-3']);

        Passport::actingAs($this->user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Test Workspace',
            'slug' => 'test',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slug' => 'test-4',
                    'client_id' => $this->clientId,
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
        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client 2',
            'secret' => 'test-secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $client2Id = $client2->id;

        $workspace1 = Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create(['name' => 'Client 1 Workspace']);
        $workspace2 = Workspace::factory()->forUser($this->user)->forClient($client2Id)->create(['name' => 'Client 2 Workspace']);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/workspaces');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $workspaceIds = collect($response->json('data'))->pluck('id');
        expect($workspaceIds)->toContain($workspace1->id);
        expect($workspaceIds)->not->toContain($workspace2->id);
    });

    it('can show a specific workspace for current client', function () {
        $workspace = Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create([
            'name' => 'My Workspace',
            'slug' => 'my-workspace',
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $workspace->id,
                    'name' => 'My Workspace',
                    'slug' => 'my-workspace',
                    'user_id' => $this->user->id,
                    'client_id' => $this->clientId,
                ],
            ]);
    });

    it('cannot show workspace belonging to different client', function () {
        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client 2',
            'secret' => 'test-secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $client2Id = $client2->id;

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client2Id)->create();

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(404);
    });

    it('cannot show workspace belonging to another user', function () {
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->forUser($otherUser)->forClient($this->clientId)->create();

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(403);
    });

    it('can update a workspace for current client', function () {
        $workspace = Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create([
            'name' => 'Old Name',
            'slug' => 'old-slug',
        ]);

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'New Name',
            'slug' => 'new-slug',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $workspace->id,
                    'name' => 'New Name',
                    'slug' => 'new-slug',
                    'user_id' => $this->user->id,
                    'client_id' => $this->clientId,
                ],
                'message' => 'Workspace updated successfully',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'New Name',
            'slug' => 'new-slug',
            'client_id' => $this->clientId,
        ]);
    });

    it('generates unique slug when updating with conflicting slug within client', function () {
        $workspace1 = Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create(['slug' => 'existing']);
        $workspace2 = Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create(['name' => 'Second']);

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/workspaces/{$workspace2->id}", [
            'slug' => 'existing',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'existing-2',
                    'client_id' => $this->clientId,
                ],
            ]);
    });

    it('cannot update workspace belonging to different client', function () {
        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client 2',
            'secret' => 'test-secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $client2Id = $client2->id;

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client2Id)->create();

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(404);
    });

    it('cannot update workspace belonging to another user', function () {
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->forUser($otherUser)->forClient($this->clientId)->create();

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/workspaces/{$workspace->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(403);
    });

    it('can delete a workspace for current client', function () {
        $workspace = Workspace::factory()->forUser($this->user)->forClient($this->clientId)->create();

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Workspace deleted successfully',
            ]);

        $this->assertDatabaseMissing('workspaces', [
            'id' => $workspace->id,
        ]);
    });

    it('cannot delete workspace belonging to different client', function () {
        $client2 = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client 2',
            'secret' => 'test-secret-2',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
        $client2Id = $client2->id;

        $workspace = Workspace::factory()->forUser($this->user)->forClient($client2Id)->create();

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    });

    it('cannot delete workspace belonging to another user', function () {
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->forUser($otherUser)->forClient($this->clientId)->create();

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/workspaces/{$workspace->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    });



    it('requires authentication for all workspace endpoints', function () {
        $workspace = Workspace::factory()->forClient($this->clientId)->create();

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
