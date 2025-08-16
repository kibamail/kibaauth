/**
 * Shared type definitions for the KibaAuth SDK
 * These types match the actual API response structures from the Laravel backend
 */

/**
 * Permission information interface
 */
export interface Permission {
  /** Permission ID */
  id: string;
  /** Permission name */
  name: string;
  /** Permission description */
  description: string | null;
  /** Permission slug */
  slug: string;
  /** Client ID this permission belongs to */
  client_id: string;
  /** Timestamp when permission was created */
  created_at: string;
  /** Timestamp when permission was last updated */
  updated_at: string;
}

/**
 * Team member information interface
 */
export interface TeamMember {
  /** Team member ID */
  id: string;
  /** Team ID this member belongs to */
  team_id: string;
  /** User ID (null for email-only invitations) */
  user_id: string | null;
  /** Email address for invitation (null if user_id is provided) */
  email: string | null;
  /** Member status */
  status: 'active' | 'pending';
  /** Timestamp when member was created */
  created_at: string;
  /** Timestamp when member was last updated */
  updated_at: string;
  /** User data (if user_id is provided and relationship is loaded) */
  user?: {
    id: string;
    email: string;
    [key: string]: any;
  };
}

/**
 * Team information interface
 */
export interface Team {
  /** Team ID */
  id: string;
  /** Team name */
  name: string;
  /** Team description */
  description: string | null;
  /** Team slug */
  slug: string;
  /** Workspace ID this team belongs to */
  workspace_id: string;
  /** Timestamp when team was created */
  created_at: string;
  /** Timestamp when team was last updated */
  updated_at: string;
  /** Team permissions (if relationship is loaded) */
  permissions?: Permission[];
  /** Team members (if relationship is loaded) */
  teamMembers?: TeamMember[];
}

/**
 * Workspace information interface
 */
export interface Workspace {
  /** Workspace ID */
  id: string;
  /** Workspace name */
  name: string;
  /** Workspace slug */
  slug: string;
  /** User ID who owns this workspace */
  user_id: string;
  /** Client ID this workspace belongs to */
  client_id: string;
  /** Timestamp when workspace was created */
  created_at: string;
  /** Timestamp when workspace was last updated */
  updated_at: string;
  /** Teams belonging to this workspace (if relationship is loaded) */
  teams?: Team[];
}

/**
 * Pending invitation information interface
 */
export interface PendingInvitation {
  /** Invitation ID (team member ID) */
  invitation_id: string;
  /** Team ID */
  team_id: string;
  /** Team name */
  team_name: string;
  /** Workspace information */
  workspace: {
    id: string;
    name: string;
    slug: string;
    created_at: string;
    updated_at: string;
  };
  /** Timestamp when invitation was sent */
  invited_at: string;
  /** Invitation status */
  status: string;
}

/**
 * User profile information interface
 * This matches the response from UserDataService::assembleUserData()
 */
export interface UserProfile {
  /** User ID */
  id: string;
  /** User's email address */
  email: string;
  /** Email verification status */
  email_verified_at: string | null;
  /** Timestamp when user was created */
  created_at: string;
  /** Timestamp when user was last updated */
  updated_at: string;
  /** User's workspaces with teams */
  workspaces: Workspace[];
  /** Pending team invitations */
  pending_invitations: PendingInvitation[];
  /** Available client permissions */
  client_permissions: Permission[];
}

/**
 * Workspace creation request interface
 */
export interface CreateWorkspaceRequest {
  /** Workspace name (required) */
  name: string;
  /** Workspace slug (optional - will be auto-generated if not provided) */
  slug?: string;
}

/**
 * Workspace update request interface
 */
export interface UpdateWorkspaceRequest {
  /** Workspace name (optional) */
  name?: string;
  /** Workspace slug (optional) */
  slug?: string;
}

/**
 * Team creation request interface
 */
export interface CreateTeamRequest {
  /** Team name (required) */
  name: string;
  /** Team description (optional) */
  description?: string;
  /** Team slug (optional - will be auto-generated if not provided) */
  slug?: string;
  /** Permission IDs to assign to the team (optional) */
  permission_ids?: string[];
}

/**
 * Team update request interface
 */
export interface UpdateTeamRequest {
  /** Team name (optional) */
  name?: string;
  /** Team description (optional) */
  description?: string;
  /** Team slug (optional) */
  slug?: string;
  /** Permission IDs to assign to the team (optional) */
  permission_ids?: string[];
}

/**
 * Team member creation request interface
 */
export interface CreateTeamMemberRequest {
  /** User ID to add (use either user_id or email) */
  user_id?: string;
  /** Email address to invite (use either user_id or email) */
  email?: string;
  /** Member status (defaults to 'pending') */
  status?: 'active' | 'pending';
}

/**
 * Sync permissions request interface
 */
export interface SyncPermissionsRequest {
  /** Array of permission IDs to sync */
  permission_ids: string[];
}

/**
 * API response wrapper interfaces
 */
export interface ApiResponse<T> {
  /** Response data */
  data: T;
  /** Response message */
  message?: string;
}

export interface ApiListResponse<T> {
  /** Array of response data */
  data: T[];
}

/**
 * Specific API response types
 */
export type UserResponse = ApiResponse<UserProfile>;
export type WorkspaceResponse = ApiResponse<Workspace>;
export type WorkspacesResponse = ApiListResponse<Workspace>;
export type TeamResponse = ApiResponse<Team>;
export type TeamsResponse = ApiListResponse<Team>;
export type TeamMemberResponse = ApiResponse<TeamMember>;
export type PermissionsResponse = ApiListResponse<Permission>;
