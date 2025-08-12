# Client Permissions Documentation

This document describes the permission system for OAuth clients in the KibaAuth application.

## Overview

The permission system allows OAuth clients to define their own set of permissions that can be used for role-based access control (RBAC). Each permission belongs to a specific OAuth client and has a unique slug within that client's scope.

## Database Schema

### Permissions Table

```sql
CREATE TABLE permissions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  slug VARCHAR(255) NOT NULL,
  client_id CHAR(36) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  
  FOREIGN KEY (client_id) REFERENCES oauth_clients(id) ON DELETE CASCADE,
  UNIQUE KEY unique_client_slug (slug, client_id),
  INDEX idx_client_id (client_id),
  INDEX idx_slug (slug)
);
```

## Key Features

- **Client-Scoped Permissions**: Each permission belongs to an OAuth client
- **Unique Slugs per Client**: Slugs are unique within each client's scope
- **Cross-Client Slug Reuse**: Different clients can have permissions with the same slug
- **Automatic Slug Generation**: Slugs are auto-generated from names if not provided
- **Conflict Resolution**: Automatic slug increment when conflicts occur (`read-users`, `read-users-2`, etc.)

## Artisan Commands

### Create Permission

Creates a new permission for an OAuth client.

```bash
php artisan client:permission {client} {name} [options]
```

#### Arguments
- `client` - The OAuth client ID or name
- `name` - The permission name

#### Options
- `--slug=SLUG` - Custom slug for the permission (optional)
- `--description=DESCRIPTION` - Description of the permission (optional)

#### Examples

```bash
# Create permission with auto-generated slug
php artisan client:permission "My App" "Read Users"

# Create permission with custom slug
php artisan client:permission "My App" "Write Users" --slug="write-user-data"

# Create permission with description
php artisan client:permission "My App" "Delete Users" --description="Permission to delete user accounts"

# Create permission with both custom slug and description
php artisan client:permission "My App" "Admin Access" --slug="admin" --description="Full administrative access"

# Use client ID instead of name
php artisan client:permission "01989da4-e73f-7033-9bf1-0add34db7152" "Update Settings"
```

#### Slug Conflict Resolution

When a slug already exists for the same client, the system automatically appends a number:

```bash
# First permission
php artisan client:permission "My App" "Read Data" --slug="read"
# Creates: slug = "read"

# Second permission with same slug
php artisan client:permission "My App" "Read Files" --slug="read"
# Creates: slug = "read-2"

# Third permission with same base slug
php artisan client:permission "My App" "Read Logs" --slug="read"
# Creates: slug = "read-3"
```

### List Permissions

Lists all permissions for an OAuth client.

```bash
php artisan client:permissions {client}
```

#### Arguments
- `client` - The OAuth client ID or name

#### Examples

```bash
# List permissions by client name
php artisan client:permissions "My App"

# List permissions by client ID
php artisan client:permissions "01989da4-e73f-7033-9bf1-0add34db7152"
```

#### Sample Output

```
Permissions for client: My App (ID: 01989da4-e73f-7033-9bf1-0add34db7152)

+----+-------------+--------------+--------------------------------+---------------------+
| ID | Name        | Slug         | Description                    | Created At          |
+----+-------------+--------------+--------------------------------+---------------------+
| 3  | Admin Access| admin        | Full administrative access     | 2025-08-12 09:45:30 |
| 2  | Write Users | write-users  | Permission to write user data  | 2025-08-12 09:44:15 |
| 1  | Read Users  | read-users   | Permission to read user data   | 2025-08-12 09:43:20 |
+----+-------------+--------------+--------------------------------+---------------------+
Total permissions: 3
```

## Model Relationships

### Permission Model

```php
use App\Models\Permission;

// Create permission
$permission = Permission::create([
    'name' => 'Read Users',
    'description' => 'Permission to read user data',
    'slug' => 'read-users', // Optional - auto-generated if not provided
    'client_id' => $client->id,
]);

// Access client
$client = $permission->client;
```

### Client Model

```php
use App\Models\Client;

// Get client permissions
$client = Client::find($clientId);
$permissions = $client->permissions;

// Create permission for client
$permission = $client->permissions()->create([
    'name' => 'New Permission',
    'description' => 'Permission description',
]);
```

## Slug Generation Rules

1. **Automatic Generation**: If no slug is provided, it's generated from the permission name
2. **Sanitization**: Names are converted to lowercase, special characters removed, spaces replaced with hyphens
3. **Client Scope**: Slugs only need to be unique within the same client
4. **Conflict Resolution**: When conflicts occur, numbers are appended (`-2`, `-3`, etc.)

### Examples

| Permission Name | Generated Slug |
|----------------|----------------|
| `Read Users` | `read-users` |
| `Write & Delete Data` | `write-delete-data` |
| `Admin (Full Access)` | `admin-full-access` |
| `API Access Level 1` | `api-access-level-1` |

## Error Handling

### Client Not Found

```bash
$ php artisan client:permission "Invalid Client" "Test Permission"

Client not found: Invalid Client
Available clients:
+--------------------------------------+----------+
| ID                                   | Name     |
+--------------------------------------+----------+
| 01989da4-e73f-7033-9bf1-0add34db7152 | My App   |
| 01989da5-1234-5678-9abc-def012345678 | Other App|
+--------------------------------------+----------+
```

### No Permissions Found

```bash
$ php artisan client:permissions "My App"

No permissions found for client: My App
Use 'php artisan client:permission' to create permissions.
```

## Best Practices

### Permission Naming

- Use clear, descriptive names: `Read Users`, `Write Posts`, `Delete Comments`
- Follow consistent naming patterns: `{Action} {Resource}`
- Avoid abbreviations unless they're standard in your domain

### Slug Conventions

- Use kebab-case: `read-users`, `write-posts`, `admin-access`
- Keep slugs short but descriptive
- Avoid special characters and spaces

### Descriptions

- Provide clear descriptions for better understanding
- Explain what the permission allows the holder to do
- Include any important limitations or scope

## Integration with RBAC

This permission system is designed to be the foundation for a role-based access control system where:

1. **Clients** define their available permissions
2. **Roles** can be assigned multiple permissions
3. **Users** can be assigned roles within workspaces
4. **API endpoints** can check for specific permissions

## Usage in Code

### Creating Permissions Programmatically

```php
use App\Models\Client;
use App\Models\Permission;

$client = Client::find($clientId);

// Create permission with auto-generated slug
$permission = $client->permissions()->create([
    'name' => 'Read Users',
    'description' => 'Permission to read user data',
]);

// Create permission with custom slug
$permission = Permission::create([
    'name' => 'Admin Access',
    'slug' => 'admin',
    'description' => 'Full administrative access',
    'client_id' => $client->id,
]);
```

### Checking Permissions (Future Implementation)

```php
// Future RBAC implementation example
if ($user->hasPermission('read-users', $workspace)) {
    // User has permission to read users in this workspace
}

if ($user->can('read-users', $workspace)) {
    // Alternative syntax using Laravel's authorization
}
```

## Command Reference

| Command | Purpose | Arguments | Options |
|---------|---------|-----------|---------|
| `client:permission` | Create a permission | `client`, `name` | `--slug`, `--description` |
| `client:permissions` | List permissions | `client` | None |

## Testing

The permission system includes comprehensive tests covering:

- **Model Tests**: Slug generation, relationships, validation
- **Command Tests**: All command scenarios and edge cases
- **Factory Tests**: Permission creation with various configurations
- **Integration Tests**: Client-permission relationships

Run permission tests with:

```bash
php artisan test --filter=PermissionTest
```

## Future Enhancements

This permission system is designed to support:

- **Role Creation**: Grouping permissions into roles
- **User-Role Assignment**: Assigning roles to users within workspaces
- **Permission Checking**: Middleware and policies for API endpoints
- **Permission Inheritance**: Hierarchical permission structures
- **Scope-based Permissions**: Different permission levels for different resources