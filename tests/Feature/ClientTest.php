<?php

use App\Models\Client;
use App\Models\Permission;

describe('Client Model', function () {
    /**
     * Test that verifies default permissions are automatically created when a new client is created.
     *
     * This test ensures that:
     * 1. A new OAuth client can be created successfully
     * 2. Default permissions are automatically generated via the Client model boot method
     * 3. All 8 expected permissions are created with correct slugs
     * 4. Permission details (name, description, client_id) are properly set
     * 5. The permissions are associated with the correct client
     *
     * The expected permissions are:
     * - teams:create, teams:update, teams:delete, teams:view
     * - teamMembers:create, teamMembers:update, teamMembers:delete, teamMembers:view
     *
     * @test
     */
    it('automatically creates default permissions when client is created', function () {
        $client = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => 'test-secret',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        $permissions = Permission::where('client_id', $client->id)->get();

        expect($permissions)->toHaveCount(8);

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

        $actualSlugs = $permissions->pluck('slug')->toArray();
        foreach ($expectedSlugs as $expectedSlug) {
            expect($actualSlugs)->toContain($expectedSlug);
        }

        $createTeamPermission = $permissions->where('slug', 'teams:create')->first();
        expect($createTeamPermission->name)->toBe('Create Teams');
        expect($createTeamPermission->description)->toBe('Permission to create teams within workspaces');
        expect($createTeamPermission->client_id)->toBe($client->id);

        $viewMembersPermission = $permissions->where('slug', 'teamMembers:view')->first();
        expect($viewMembersPermission->name)->toBe('View Team Members');
        expect($viewMembersPermission->description)->toBe('Permission to view and list team members');
        expect($viewMembersPermission->client_id)->toBe($client->id);
    });
});
