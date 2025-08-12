<?php

use App\Models\Client;
use App\Models\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->client = Client::factory()->create([
        'name' => 'Test Client',
    ]);

    $this->otherClient = Client::factory()->create([
        'name' => 'Other Client',
    ]);
});

describe('Permission Model', function () {
    it('can create a permission with all attributes', function () {
        $permission = Permission::create([
            'name' => 'Read Users',
            'description' => 'Permission to read user data',
            'slug' => 'read-users',
            'client_id' => $this->client->id,
        ]);

        expect($permission->name)->toBe('Read Users');
        expect($permission->description)->toBe('Permission to read user data');
        expect($permission->slug)->toBe('read-users');
        expect($permission->client_id)->toBe($this->client->id);
    });

    it('generates unique slug on creation when not provided', function () {
        $permission = Permission::create([
            'name' => 'Write Users',
            'client_id' => $this->client->id,
        ]);

        expect($permission->slug)->toBe('write-users');
    });

    it('uses provided slug if available', function () {
        $permission = Permission::create([
            'name' => 'Custom Permission',
            'slug' => 'custom-perm',
            'client_id' => $this->client->id,
        ]);

        expect($permission->slug)->toBe('custom-perm');
    });

    it('generates unique slug when slug conflicts within same client', function () {
        // Create first permission
        Permission::create([
            'name' => 'Read Data',
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

        // Create second permission with same base slug for same client
        $permission2 = Permission::create([
            'name' => 'Read Data Again',
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

        expect($permission2->slug)->toBe('read-data-2');
    });

    it('allows same slug for different clients', function () {
        // Create permission for first client
        $permission1 = Permission::create([
            'name' => 'Read Data',
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

        // Create permission with same slug for different client
        $permission2 = Permission::create([
            'name' => 'Read Data',
            'slug' => 'read-data',
            'client_id' => $this->otherClient->id,
        ]);

        expect($permission1->slug)->toBe('read-data');
        expect($permission2->slug)->toBe('read-data');
        expect($permission1->client_id)->toBe($this->client->id);
        expect($permission2->client_id)->toBe($this->otherClient->id);
    });

    it('handles multiple slug conflicts correctly', function () {
        // Create permissions with conflicting base slug for same client
        $permission1 = Permission::create(['name' => 'Test', 'client_id' => $this->client->id]);
        $permission2 = Permission::create(['name' => 'Test', 'client_id' => $this->client->id]);
        $permission3 = Permission::create(['name' => 'Test', 'client_id' => $this->client->id]);

        expect($permission1->slug)->toBe('test');
        expect($permission2->slug)->toBe('test-2');
        expect($permission3->slug)->toBe('test-3');
    });

    it('belongs to a client', function () {
        $permission = Permission::factory()->forClient($this->client)->create();

        expect($permission->client)->toBeInstanceOf(Client::class);
        expect($permission->client->id)->toBe($this->client->id);
    });

    it('can be accessed through client relationship', function () {
        $permission = Permission::factory()->forClient($this->client)->create();

        // Client automatically gets 8 default permissions + 1 created = 9 total
        expect($this->client->permissions)->toHaveCount(9);
        expect($this->client->permissions->where('id', $permission->id))->toHaveCount(1);
    });
});

describe('Client Permission Artisan Command', function () {
    it('creates permission successfully with valid client name', function () {
        $exitCode = Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Read Users',
            '--description' => 'Permission to read user data',
        ]);

        expect($exitCode)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Read Users',
            'slug' => 'read-users',
            'description' => 'Permission to read user data',
            'client_id' => $this->client->id,
        ]);
    });

    it('creates permission successfully with valid client ID', function () {
        $exitCode = Artisan::call('client:permission', [
            'client' => $this->client->id,
            'name' => 'Write Users',
            '--description' => 'Permission to write user data',
        ]);

        expect($exitCode)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Write Users',
            'slug' => 'write-users',
            'description' => 'Permission to write user data',
            'client_id' => $this->client->id,
        ]);
    });

    it('creates permission with custom slug', function () {
        $exitCode = Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Delete Users',
            '--slug' => 'delete-user-data',
            '--description' => 'Permission to delete user data',
        ]);

        expect($exitCode)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Delete Users',
            'slug' => 'delete-user-data',
            'description' => 'Permission to delete user data',
            'client_id' => $this->client->id,
        ]);
    });

    it('creates permission without description', function () {
        $exitCode = Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Basic Permission',
        ]);

        expect($exitCode)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Basic Permission',
            'slug' => 'basic-permission',
            'description' => null,
            'client_id' => $this->client->id,
        ]);
    });

    it('handles slug conflicts automatically', function () {
        // Create first permission
        Permission::create([
            'name' => 'Existing Permission',
            'slug' => 'existing',
            'client_id' => $this->client->id,
        ]);

        // Try to create another with same slug via command
        $exitCode = Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Another Permission',
            '--slug' => 'existing',
        ]);

        expect($exitCode)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Another Permission',
            'slug' => 'existing-2',
            'client_id' => $this->client->id,
        ]);

        // Check that the original permission still exists
        $this->assertDatabaseHas('permissions', [
            'slug' => 'existing',
            'client_id' => $this->client->id,
        ]);
    });

    it('allows same slug for different clients', function () {
        // Create permission for first client
        $exitCode1 = Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Read Data',
            '--slug' => 'read-data',
        ]);

        // Create permission with same slug for different client
        $exitCode2 = Artisan::call('client:permission', [
            'client' => 'Other Client',
            'name' => 'Read Data',
            '--slug' => 'read-data',
        ]);

        expect($exitCode1)->toBe(0);
        expect($exitCode2)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'read-data',
            'client_id' => $this->otherClient->id,
        ]);
    });

    it('fails when client does not exist', function () {
        $exitCode = Artisan::call('client:permission', [
            'client' => 'Nonexistent Client',
            'name' => 'Some Permission',
        ]);

        expect($exitCode)->toBe(1);

        $this->assertDatabaseMissing('permissions', [
            'name' => 'Some Permission',
        ]);
    });

    it('handles special characters in permission names', function () {
        $exitCode = Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Read & Write Users (Admin)',
            '--description' => 'Complex permission with special chars',
        ]);

        expect($exitCode)->toBe(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'Read & Write Users (Admin)',
            'slug' => 'read-write-users-admin',
            'description' => 'Complex permission with special chars',
            'client_id' => $this->client->id,
        ]);
    });

    it('displays command output correctly', function () {
        Artisan::call('client:permission', [
            'client' => 'Test Client',
            'name' => 'Test Permission',
            '--description' => 'Test description',
        ]);

        $output = Artisan::output();

        expect($output)->toContain('Permission created successfully!');
        expect($output)->toContain('Test Permission');
        expect($output)->toContain('test-permission');
        expect($output)->toContain('Test description');
        expect($output)->toContain('Test Client');
    });

    it('shows available clients when client not found', function () {
        Artisan::call('client:permission', [
            'client' => 'Invalid Client',
            'name' => 'Test Permission',
        ]);

        $output = Artisan::output();

        expect($output)->toContain('Client not found: Invalid Client');
        expect($output)->toContain('Available clients:');
        expect($output)->toContain('Test Client');
        expect($output)->toContain('Other Client');
    });
});

describe('Permission Factory', function () {
    it('creates permission with factory', function () {
        $permission = Permission::factory()->forClient($this->client)->create([
            'name' => 'Factory Permission',
        ]);

        expect($permission->name)->toBe('Factory Permission');
        expect($permission->client_id)->toBe($this->client->id);
        expect($permission->slug)->toBeString();
    });

    it('creates permission with custom attributes', function () {
        $permission = Permission::factory()
            ->forClient($this->client)
            ->withName('Custom Name')
            ->withSlug('custom-slug')
            ->withDescription('Custom description')
            ->create();

        expect($permission->name)->toBe('Custom Name');
        expect($permission->slug)->toBe('custom-slug');
        expect($permission->description)->toBe('Custom description');
        expect($permission->client_id)->toBe($this->client->id);
    });

    it('creates permission without description', function () {
        $permission = Permission::factory()
            ->forClient($this->client)
            ->withoutDescription()
            ->create();

        expect($permission->description)->toBeNull();
    });
});

describe('List Client Permissions Command', function () {
    it('lists permissions for a client successfully', function () {
        // Create some permissions for the client
        Permission::factory()->forClient($this->client)->create([
            'name' => 'Read Users',
            'slug' => 'read-users',
            'description' => 'Permission to read user data',
        ]);

        Permission::factory()->forClient($this->client)->create([
            'name' => 'Write Users',
            'slug' => 'write-users',
            'description' => 'Permission to write user data',
        ]);

        $exitCode = Artisan::call('client:permissions', [
            'client' => 'Test Client',
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Permissions for client: Test Client');
        expect($output)->toContain('Read Users');
        expect($output)->toContain('Write Users');
        expect($output)->toContain('read-users');
        expect($output)->toContain('write-users');
        expect($output)->toContain('Total permissions: 10');
    });

    it('shows client with only default permissions', function () {
        $exitCode = Artisan::call('client:permissions', [
            'client' => 'Test Client',
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Permissions for client: Test Client');
        expect($output)->toContain('Total permissions: 8');
        expect($output)->toContain('teams:create');
        expect($output)->toContain('teamMembers:view');
    });

    it('fails when client does not exist for list command', function () {
        $exitCode = Artisan::call('client:permissions', [
            'client' => 'Nonexistent Client',
        ]);

        expect($exitCode)->toBe(1);

        $output = Artisan::output();
        expect($output)->toContain('Client not found: Nonexistent Client');
        expect($output)->toContain('Available clients:');
    });

    it('works with client ID instead of name', function () {
        Permission::factory()->forClient($this->client)->create([
            'name' => 'Admin Permission',
        ]);

        $exitCode = Artisan::call('client:permissions', [
            'client' => $this->client->id,
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Admin Permission');
        expect($output)->toContain('Total permissions: 9');
    });

    it('orders permissions by creation date descending', function () {
        // Create permissions with slight delays to ensure different timestamps
        $permission1 = Permission::factory()->forClient($this->client)->create(['name' => 'First Permission']);
        sleep(1);
        $permission2 = Permission::factory()->forClient($this->client)->create(['name' => 'Second Permission']);

        $exitCode = Artisan::call('client:permissions', [
            'client' => 'Test Client',
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        // Check that Second Permission appears before First Permission in output
        $firstPos = strpos($output, 'First Permission');
        $secondPos = strpos($output, 'Second Permission');
        expect($secondPos)->toBeLessThan($firstPos);
    });

    it('automatically attaches new permissions to existing Administrators teams', function () {
        // Create a workspace which will have an Administrators team
        $workspace1 = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $this->client->id,
        ]);

        $workspace2 = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $this->client->id,
        ]);

        // Verify Administrators teams exist and have 8 default permissions
        $adminTeam1 = $workspace1->teams()->where('name', 'Administrators')->first();
        $adminTeam2 = $workspace2->teams()->where('name', 'Administrators')->first();

        expect($adminTeam1)->not->toBeNull();
        expect($adminTeam2)->not->toBeNull();
        expect($adminTeam1->permissions)->toHaveCount(8);
        expect($adminTeam2->permissions)->toHaveCount(8);

        // Create a new permission via command
        $exitCode = Artisan::call('client:permission', [
            'client' => $this->client->id,
            'name' => 'Custom Permission',
            '--description' => 'A custom permission for testing',
        ]);

        expect($exitCode)->toBe(0);

        // Verify the permission was created
        $newPermission = Permission::where('client_id', $this->client->id)
            ->where('name', 'Custom Permission')
            ->first();
        expect($newPermission)->not->toBeNull();

        // Verify the permission was automatically attached to both Administrators teams
        $adminTeam1->refresh();
        $adminTeam2->refresh();

        expect($adminTeam1->permissions)->toHaveCount(9); // 8 default + 1 new
        expect($adminTeam2->permissions)->toHaveCount(9); // 8 default + 1 new

        expect($adminTeam1->permissions->contains('id', $newPermission->id))->toBeTrue();
        expect($adminTeam2->permissions->contains('id', $newPermission->id))->toBeTrue();

        // Verify command output mentions the attachment
        $output = Artisan::output();
        expect($output)->toContain('Permission attached to Administrators team in workspace:');
    });
});
