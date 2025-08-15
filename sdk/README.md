# @kibaauth/sdk

A TypeScript SDK for interacting with the KibaAuth authentication service.

## Installation

First, configure npm to use GitHub Package Registry for the `@kibamail` scope:

```bash
echo "@kibamail:registry=https://npm.pkg.github.com" >> ~/.npmrc
```

Then authenticate with GitHub Packages using a Personal Access Token:

```bash
npm login --scope=@kibamail --registry=https://npm.pkg.github.com
```

Finally, install the package:

```bash
npm install @kibamail/auth-sdk
```

## Usage

```typescript
import { Kibaauth } from '@kibamail/auth-sdk';

// Initialize the SDK
const kibaauth = new Kibaauth({
  apiUrl: 'https://your-kibaauth-instance.com',
  apiKey: 'your-api-key'
});

// Example: Get user information
const user = await kibaauth.user.get(userId);

// Example: Authenticate user
const authResult = await kibaauth.auth.login({
  email: 'user@example.com',
  password: 'password'
});
```

## Features

- **Authentication**: Login, logout, and token management
- **User Management**: Create, read, update user profiles
- **Team Management**: Manage teams and team members
- **Workspace Management**: Handle workspace operations
- **TypeScript Support**: Full type definitions included
- **Modern ES Modules**: Supports both CommonJS and ES modules

## API Reference

### Authentication

```typescript
// Login
const result = await kibaauth.auth.login({
  email: 'user@example.com',
  password: 'password'
});

// Logout
await kibaauth.auth.logout();

// Refresh token
const newToken = await kibaauth.auth.refresh();
```

### User Management

```typescript
// Get user
const user = await kibaauth.user.get(userId);

// Update user
const updatedUser = await kibaauth.user.update(userId, {
  name: 'New Name',
  email: 'new@example.com'
});
```

### Team Management

```typescript
// Get team
const team = await kibaauth.team.get(teamId);

// Create team
const newTeam = await kibaauth.team.create({
  name: 'My Team',
  description: 'Team description'
});
```

### Workspace Management

```typescript
// Get workspace
const workspace = await kibaauth.workspace.get(workspaceId);

// Update workspace
const updatedWorkspace = await kibaauth.workspace.update(workspaceId, {
  name: 'New Workspace Name'
});
```

## Configuration

The SDK accepts the following configuration options:

```typescript
interface KibaauthConfig {
  apiUrl: string;          // Base URL of your KibaAuth instance
  apiKey?: string;         // API key for authentication
  timeout?: number;        // Request timeout in milliseconds (default: 10000)
  retries?: number;        // Number of retry attempts (default: 3)
}
```

## Error Handling

The SDK uses structured error handling:

```typescript
try {
  const user = await kibaauth.user.get(userId);
} catch (error) {
  if (error.status === 404) {
    console.log('User not found');
  } else if (error.status === 401) {
    console.log('Unauthorized');
  } else {
    console.log('An error occurred:', error.message);
  }
}
```

## Development

### Building

```bash
npm run build
```

### Testing

```bash
npm test
```

### Type Checking

```bash
npm run typecheck
```

## License

MIT

## GitHub Package Registry

This package is published to GitHub Package Registry. To use it:

1. **Configure npm**: Add GitHub Package Registry for the `@kibamail` scope
   ```bash
   echo "@kibamail:registry=https://npm.pkg.github.com" >> ~/.npmrc
   ```

2. **Authenticate**: Create a GitHub Personal Access Token with `read:packages` scope
   ```bash
   npm login --scope=@kibamail --registry=https://npm.pkg.github.com
   ```

3. **Install**: Install the package
   ```bash
   npm install @kibamail/auth-sdk
   ```

## Support

For support and questions, please visit [GitHub Issues](https://github.com/kibamail/kibaauth/issues).

## Related Projects

- [KibaMail](https://kibamail.com) - Email marketing platform
- [KibaShip](https://kibaship.com) - Shipping management
- [KibaMeet](https://kibameet.com) - Meeting platform
- [KibaChat](https://kibachat.com) - Chat platform