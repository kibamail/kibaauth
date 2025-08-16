/**
 * @kibaauth/sdk - Official TypeScript/JavaScript SDK for Kibaauth API
 *
 * This SDK provides a simple and intuitive interface for integrating with the Kibaauth
 * OAuth2 authentication service. It supports the complete OAuth2 flow including
 * authorization URL generation, token exchange, and token management.
 *
 * @example Basic Usage
 * ```typescript
 * import { Kibaauth } from '@kibaauth/sdk'
 *
 * const api = new Kibaauth()
 *   .clientId('your-client-id')
 *   .clientSecret('your-client-secret')
 *   .callbackUrl('https://your-app.com/callback')
 *   .api()
 *
 * // Exchange authorization code for access token
 * const response = await api.auth().accessToken('authorization-code')
 * ```
 *
 * @packageDocumentation
 */

// Main SDK classes
export { ApiClient } from './ApiClient';
export { Auth } from './Auth';
export { Kibaauth } from './Kibaauth';
export { Team } from './Team';
export { User } from './User';
export { Workspace } from './Workspace';

// Import for default export
import { Kibaauth } from './Kibaauth';

// Type definitions
export type { KibaauthConfig } from './Kibaauth';

export type { ApiClientConfig, RequestOptions } from './ApiClient';

export type {
  AccessTokenResponse,
  AuthorizationUrlParams,
  RefreshTokenResponse,
} from './Auth';

export type {
  CreateTeamMemberRequest,
  CreateTeamRequest,
  CreateWorkspaceRequest,
  PendingInvitation,
  Permission,
  SyncPermissionsRequest,
  Team as TeamData,
  TeamMember,
  TeamMemberResponse,
  TeamResponse,
  TeamsResponse,
  UpdateTeamRequest,
  UpdateWorkspaceRequest,
  UserProfile,
  Workspace as WorkspaceData,
  WorkspaceResponse,
  WorkspacesResponse,
} from './types';

/**
 * Default export for convenience
 *
 * @example
 * ```typescript
 * import Kibaauth from '@kibaauth/sdk'
 *
 * const api = new Kibaauth()
 *   .clientId('client-id')
 *   .clientSecret('client-secret')
 *   .callbackUrl('callback-url')
 *   .api()
 * ```
 */
export default Kibaauth;
