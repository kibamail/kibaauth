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
    /**
     * Test that verifies a permission can be created with all attributes specified.
     *
     * This test ensures that:
     * 1. A permission can be created with name, description, slug, and client_id
     * 2. All provided attributes are correctly stored in the database
     * 3. The permission is properly associated with the specified client
     * 4. Custom attributes are preserved during creation
     *
     * @test
     */
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

    /**
     * Test that verifies automatic slug generation when not explicitly provided.
     *
     * This test ensures that:
     * 1. A permission can be created without specifying a slug
     * 2. The system automatically generates a slug from the permission name
     * 3. The generated slug follows kebab-case convention
     * 4. The slug generation process works correctly for name-to-slug conversion
     *
     * @test
     */
    it('generates unique slug on creation when not provided', function () {
        $permission = Permission::create([
            'name' => 'Write Users',
            'client_id' => $this->client->id,
        ]);

        expect($permission->slug)->toBe('write-users');
    });

    /**
     * Test that verifies custom slugs are preserved when explicitly provided.
     *
     * This test ensures that:
     * 1. A permission can be created with a custom slug
     * 2. The provided slug is used instead of auto-generating one
     * 3. Custom slug values are preserved exactly as specified
     * 4. Manual slug control is available when needed
     *
     * @test
     */
    it('uses provided slug if available', function () {
        $permission = Permission::create([
            'name' => 'Custom Permission',
            'slug' => 'custom-perm',
            'client_id' => $this->client->id,
        ]);

        expect($permission->slug)->toBe('custom-perm');
    });

    /**
     * Test that verifies unique slug generation when conflicts occur within the same client.
     *
     * This test ensures that:
     * 1. Multiple permissions can be created with the same base slug for one client
     * 2. The system automatically resolves slug conflicts by appending numbers
     * 3. The second permission gets a "-2" suffix to ensure uniqueness
     * 4. Slug uniqueness is enforced within the client scope
     *
     * @test
     */
    it('generates unique slug when slug conflicts within same client', function () {
        Permission::create([
            'name' => 'Read Data',
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

        $permission2 = Permission::create([
            'name' => 'Read Data Again',
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

        expect($permission2->slug)->toBe('read-data-2');
    });

    /**
     * Test that verifies same slugs are allowed for different clients.
     *
     * This test ensures that:
     * 1. Different clients can have permissions with identical slugs
     * 2. Slug uniqueness is scoped to individual clients, not globally
     * 3. Client isolation is maintained for permission slugs
     * 4. Multiple clients can independently use the same permission naming
     *
     * @test
     */
    it('allows same slug for different clients', function () {
        $permission1 = Permission::create([
            'name' => 'Read Data',
            'slug' => 'read-data',
            'client_id' => $this->client->id,
        ]);

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

    /**
     * Test that verifies proper handling of multiple slug conflicts within the same client.
     *
     * This test ensures that:
     * 1. Multiple permissions with the same base name can be created for one client
     * 2. Each subsequent permission gets an incremented suffix (-2, -3, etc.)
     * 3. The slug conflict resolution algorithm works for multiple conflicts
     * 4. Sequential numbering is applied correctly to maintain uniqueness
     *
     * @test
     */
    it('handles multiple slug conflicts correctly', function () {
        $permission1 = Permission::create(['name' => 'Test', 'client_id' => $this->client->id]);
        $permission2 = Permission::create(['name' => 'Test', 'client_id' => $this->client->id]);
        $permission3 = Permission::create(['name' => 'Test', 'client_id' => $this->client->id]);

        expect($permission1->slug)->toBe('test');
        expect($permission2->slug)->toBe('test-2');
        expect($permission3->slug)->toBe('test-3');
    });

    /**
     * Test that verifies the permission-client relationship is properly established.
     *
     * This test ensures that:
     * 1. A permission is correctly associated with its client
     * 2. The client relationship can be accessed via Eloquent
     * 3. The relationship returns the correct Client model instance
     * 4. The permission belongs to the expected client
     *
     * @test
     */
    it('belongs to a client', function () {
        $permission = Permission::factory()->forClient($this->client)->create();

        expect($permission->client)->toBeInstanceOf(Client::class);
        expect($permission->client->id)->toBe($this->client->id);
    });

    /**
     * Test that verifies permissions can be accessed through the client relationship.
     *
     * This test ensures that:
     * 1. A client's permissions can be accessed via the permissions relationship
     * 2. The relationship includes both default and custom permissions
     * 3. The permission count includes the 8 default permissions plus any custom ones
     * 4. The specific created permission is included in the client's permissions collection
     *
     * @test
     */
    it('can be accessed through client relationship', function () {
        $permission = Permission::factory()->forClient($this->client)->create();


        expect($this->client->permissions)->toHaveCount(9);
        expect($this->client->permissions->where('id', $permission->id))->toHaveCount(1);
    });
});

describe('Client Permission Artisan Command', function () {
    /**
     * Test that verifies permissions can be created via Artisan command using client name.
     *
     * This test ensures that:
     * 1. The client:permission Artisan command executes successfully
     * 2. A permission can be created by specifying the client name
     * 3. The permission is created with the correct attributes in the database
     * 4. The slug is automatically generated from the permission name
     * 5. The command returns a successful exit code (0)
     *
     * @test
     */
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

    /**
     * Test that verifies permissions can be created via Artisan command using client ID.
     *
     * This test ensures that:
     * 1. The client:permission Artisan command accepts client ID as parameter
     * 2. A permission can be created by specifying the client ID instead of name
     * 3. The permission is properly associated with the correct client
     * 4. All permission attributes are correctly stored in the database
     * 5. The command completes successfully with exit code 0
     *
     * @test
     */
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

    /**
     * Test that verifies permissions can be created with custom slugs via Artisan command.
     *
     * This test ensures that:
     * 1. The client:permission command accepts a custom --slug option
     * 2. The provided custom slug is used instead of auto-generating one
     * 3. Custom slugs are preserved exactly as specified
     * 4. The permission is created with all specified attributes
     * 5. Manual slug control works correctly through the command interface
     *
     * @test
     */
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

    /**
     * Test that verifies permissions can be created without optional description.
     *
     * This test ensures that:
     * 1. The description parameter is optional in the client:permission command
     * 2. Permissions can be created with only name and client specified
     * 3. The description field is set to null when not provided
     * 4. The command handles missing optional parameters gracefully
     * 5. Basic permission creation works without all optional fields
     *
     * @test
     */
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

    /**
     * Test that verifies automatic slug conflict resolution in Artisan command.
     *
     * This test ensures that:
     * 1. The command detects when a slug already exists for the same client
     * 2. Slug conflicts are automatically resolved by appending numbers
     * 3. The original permission remains unchanged during conflict resolution
     * 4. The new permission gets a unique slug (e.g., "existing-2")
     * 5. Both permissions coexist with their respective unique slugs
     *
     * @test
     */
    it('handles slug conflicts automatically', function () {
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

    /**
     * Test that verifies same slugs are allowed for different clients via Artisan command.
     *
     * This test ensures that:
     * 1. Different clients can have permissions with identical slugs
     * 2. The command properly scopes slug uniqueness to individual clients
     * 3. Both commands execute successfully without conflicts
     * 4. Each permission is associated with its respective client
     * 5. Client isolation is maintained for permission slugs
     *
     * @test
     */
    it('allows same slug for different clients', function () {
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

    /**
     * Test that verifies the command fails gracefully when client doesn't exist.
     *
     * This test ensures that:
     * 1. The command validates that the specified client exists
     * 2. Non-existent client names/IDs cause the command to fail
     * 3. The command returns a failure exit code (1) for invalid clients
     * 4. No permission is created when the client is invalid
     * 5. Proper error handling prevents orphaned permissions
     *
     * @test
     */
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

    /**
     * Test that verifies the command handles special characters in permission names.
     *
     * This test ensures that:
     * 1. Permission names with special characters are accepted
     * 2. Special characters are properly handled during slug generation
     * 3. The original name with special characters is preserved
     * 4. The generated slug follows safe URL conventions
     * 5. Complex permission names are processed correctly
     *
     * @test
     */
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

    /**
     * Test that verifies the command displays proper output messages.
     *
     * This test ensures that:
     * 1. The command provides informative output upon successful completion
     * 2. Output includes confirmation of permission creation
     * 3. Key permission details (name, slug, description, client) are displayed
     * 4. Success messages are clear and informative
     * 5. Command output helps users verify the operation results
     *
     * @test
     */
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

    /**
     * Test that verifies helpful error messages when client is not found.
     *
     * This test ensures that:
     * 1. The command provides helpful feedback when client doesn't exist
     * 2. Available clients are listed to help users find the correct name/ID
     * 3. Error messages are informative and actionable
     * 4. Users can identify valid clients from the error output
     * 5. The command fails gracefully with useful guidance
     *
     * @test
     */
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
    /**
     * Test that verifies permissions can be created using the factory.
     *
     * This test ensures that:
     * 1. The Permission factory can create permission instances
     * 2. The factory properly associates permissions with clients
     * 3. Custom attributes can be provided during factory creation
     * 4. The slug is automatically generated when not specified
     * 5. Factory-created permissions are properly persisted to database
     *
     * @test
     */
    it('creates permission with factory', function () {
        $permission = Permission::factory()->forClient($this->client)->create([
            'name' => 'Factory Permission',
        ]);

        expect($permission->name)->toBe('Factory Permission');
        expect($permission->client_id)->toBe($this->client->id);
        expect($permission->slug)->toBeString();
    });

    /**
     * Test that verifies the Permission factory supports custom attribute methods.
     *
     * This test ensures that:
     * 1. The factory provides fluent methods for setting attributes
     * 2. Custom name, slug, and description can be set via factory methods
     * 3. All custom attributes are properly applied to the created permission
     * 4. The factory maintains client association with custom attributes
     * 5. Fluent interface allows method chaining for configuration
     *
     * @test
     */
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

    /**
     * Test that verifies the Permission factory can create permissions without descriptions.
     *
     * This test ensures that:
     * 1. The factory provides a method to explicitly omit descriptions
     * 2. Permissions can be created with null description values
     * 3. The withoutDescription() method works correctly
     * 4. Optional fields can be intentionally left empty via factory methods
     * 5. Factory supports both presence and absence of optional attributes
     *
     * @test
     */
    it('creates permission without description', function () {
        $permission = Permission::factory()
            ->forClient($this->client)
            ->withoutDescription()
            ->create();

        expect($permission->description)->toBeNull();
    });
});

describe('List Client Permissions Command', function () {
    /**
     * Test that verifies the client:permissions command lists permissions for a client successfully.
     *
     * This test ensures that:
     * 1. The client:permissions command executes successfully with a valid client name
     * 2. Custom permissions created for the client are displayed in the output
     * 3. The command shows permission details including names and slugs
     * 4. The total permission count includes both default and custom permissions
     * 5. The output is properly formatted and informative
     *
     * @test
     */
    it('lists permissions for a client successfully', function () {
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

    /**
     * Test that verifies the command displays default permissions for a client without custom permissions.
     *
     * This test ensures that:
     * 1. The command works correctly for clients with only default permissions
     * 2. All 8 default permissions are displayed in the output
     * 3. Specific default permission slugs (teams:create, teamMembers:view) are shown
     * 4. The total permission count reflects only the default permissions
     * 5. Clients without custom permissions are handled properly
     *
     * @test
     */
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

    /**
     * Test that verifies the command fails gracefully when the client doesn't exist.
     *
     * This test ensures that:
     * 1. The command validates that the specified client exists
     * 2. Non-existent client names cause the command to fail appropriately
     * 3. The command returns a failure exit code (1) for invalid clients
     * 4. Helpful error messages are displayed including available clients
     * 5. Error handling provides actionable feedback to users
     *
     * @test
     */
    it('fails when client does not exist for list command', function () {
        $exitCode = Artisan::call('client:permissions', [
            'client' => 'Nonexistent Client',
        ]);

        expect($exitCode)->toBe(1);

        $output = Artisan::output();
        expect($output)->toContain('Client not found: Nonexistent Client');
        expect($output)->toContain('Available clients:');
    });

    /**
     * Test that verifies the command accepts client ID instead of client name.
     *
     * This test ensures that:
     * 1. The client:permissions command accepts client ID as a parameter
     * 2. Client ID can be used instead of client name for identification
     * 3. Permissions are correctly listed when using client ID
     * 4. The total permission count is accurate when using client ID
     * 5. Both client name and ID are supported as input methods
     *
     * @test
     */
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

    /**
     * Test that verifies permissions are ordered by creation date in descending order.
     *
     * This test ensures that:
     * 1. The command displays permissions ordered by creation date (newest first)
     * 2. Recently created permissions appear before older ones in the output
     * 3. The ordering algorithm works correctly for multiple permissions
     * 4. Timestamp-based sorting is properly implemented
     * 5. Users can easily identify the most recent permissions
     *
     * @test
     */
    it('orders permissions by creation date descending', function () {
        $permission1 = Permission::factory()->forClient($this->client)->create(['name' => 'First Permission']);
        sleep(1);
        $permission2 = Permission::factory()->forClient($this->client)->create(['name' => 'Second Permission']);

        $exitCode = Artisan::call('client:permissions', [
            'client' => 'Test Client',
        ]);

        expect($exitCode)->toBe(0);

        $output = Artisan::output();
        $firstPos = strpos($output, 'First Permission');
        $secondPos = strpos($output, 'Second Permission');
        expect($secondPos)->toBeLessThan($firstPos);
    });

    /**
     * Test that verifies new permissions are automatically attached to existing Administrators teams.
     *
     * This test ensures that:
     * 1. Workspaces have auto-created Administrators teams with default permissions
     * 2. New permissions created via command are automatically attached to all Administrators teams
     * 3. All existing Administrators teams receive the new permission simultaneously
     * 4. The permission count increases correctly for all affected teams
     * 5. The command output confirms the automatic attachment process
     * 6. Permission propagation works across multiple workspaces for the same client
     *
     * @test
     */
    it('automatically attaches new permissions to existing Administrators teams', function () {
        $workspace1 = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $this->client->id,
        ]);

        $workspace2 = Workspace::factory()->create([
            'user_id' => User::factory()->create()->id,
            'client_id' => $this->client->id,
        ]);

        $adminTeam1 = $workspace1->teams()->where('name', 'Administrators')->first();
        $adminTeam2 = $workspace2->teams()->where('name', 'Administrators')->first();

        expect($adminTeam1)->not->toBeNull();
        expect($adminTeam2)->not->toBeNull();
        expect($adminTeam1->permissions)->toHaveCount(8);
        expect($adminTeam2->permissions)->toHaveCount(8);

        $exitCode = Artisan::call('client:permission', [
            'client' => $this->client->id,
            'name' => 'Custom Permission',
            '--description' => 'A custom permission for testing',
        ]);

        expect($exitCode)->toBe(0);

        $newPermission = Permission::where('client_id', $this->client->id)
            ->where('name', 'Custom Permission')
            ->first();
        expect($newPermission)->not->toBeNull();

        $adminTeam1->refresh();
        $adminTeam2->refresh();

        expect($adminTeam1->permissions)->toHaveCount(9);
        expect($adminTeam2->permissions)->toHaveCount(9);

        expect($adminTeam1->permissions->contains('id', $newPermission->id))->toBeTrue();
        expect($adminTeam2->permissions->contains('id', $newPermission->id))->toBeTrue();

        $output = Artisan::output();
        expect($output)->toContain('Permission attached to Administrators team in workspace:');
    });
});
