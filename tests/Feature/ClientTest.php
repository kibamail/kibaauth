<?php

use App\Models\Client;
use App\Models\Permission;

describe('Client Model', function () {
    it('automatically creates default permissions when client is created', function () {
        // Create a new client
        $client = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => 'test-secret',
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);

        // Assert that 8 default permissions were created
        $permissions = Permission::where('client_id', $client->id)->get();



        expect($permissions)->toHaveCount(8);

        // Assert that all expected permissions exist with correct slugs
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

        // Assert specific permission details
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
