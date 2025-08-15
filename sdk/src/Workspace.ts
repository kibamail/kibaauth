import type { ApiClient } from './ApiClient';

/**
 * Team information interface
 */
export interface TeamInfo {
  /** Team ID */
  id: string;
  /** Team name */
  name: string;
  /** Team description */
  description?: string;
  /** Workspace ID this team belongs to */
  workspace_id: string;
  /** Timestamp when team was created */
  created_at: string;
  /** Timestamp when team was last updated */
  updated_at: string;
  /** Team permissions */
  permissions?: Permission[];
}

/**
 * Permission information interface
 */
export interface Permission {
  /** Permission ID */
  id: string;
  /** Permission name */
  name: string;
  /** Permission description */
  description?: string;
  /** Client ID this permission belongs to */
  client_id: string;
  /** Timestamp when permission was created */
  created_at: string;
  /** Timestamp when permission was last updated */
  updated_at: string;
}

/**
 * Workspace information interface
 */
export interface WorkspaceData {
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
  /** Teams belonging to this workspace */
  teams?: TeamInfo[];
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
 * API response wrapper interface
 */
export interface WorkspaceResponse {
  /** Response data */
  data: WorkspaceData;
  /** Response message */
  message?: string;
}

/**
 * List workspaces response interface
 */
export interface WorkspacesResponse {
  /** Array of workspaces */
  data: WorkspaceData[];
}

/**
 * Workspace class for handling workspace-related API operations
 *
 * @example
 * ```typescript
 * const workspace = authenticatedClient.workspaces()
 *
 * // Create a workspace
 * const [workspace, error] = await workspace.create({ name: 'My Workspace' })
 *
 * // List workspaces
 * const [workspaces, error] = await workspace.list()
 *
 * // Get a specific workspace
 * const [workspace, error] = await workspace.get('workspace-id')
 *
 * // Update a workspace
 * const [updated, error] = await workspace.update('workspace-id', { name: 'New Name' })
 *
 * // Delete a workspace
 * const [success, error] = await workspace.delete('workspace-id')
 * ```
 */
export class Workspace {
  private apiClient: ApiClient;

  /**
   * Create a new Workspace instance
   *
   * @param apiClient - The API client instance to use for requests
   */
  constructor(apiClient: ApiClient) {
    this.apiClient = apiClient;
  }

  /**
   * Create a new workspace
   *
   * @param data - Workspace creation data
   * @returns Promise resolving to a tuple [WorkspaceData | null, any]
   *
   * @example
   * ```typescript
   * const [workspace, error] = await workspace.create({
   *   name: 'My Workspace',
   *   slug: 'my-workspace'
   * })
   * if (error) {
   *   console.error('Failed to create workspace:', error)
   * } else {
   *   console.log('Created workspace:', workspace.name)
   * }
   * ```
   */
  async create(
    data: CreateWorkspaceRequest,
  ): Promise<[WorkspaceData | null, any]> {
    if (!data.name || !data.name.trim()) {
      return [null, new Error('Workspace name is required')];
    }

    const [response, error] = await this.apiClient.post<WorkspaceResponse>(
      '/api/workspaces',
      data,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * List all workspaces for the authenticated user and client
   *
   * @returns Promise resolving to a tuple [WorkspaceData[] | null, any]
   *
   * @example
   * ```typescript
   * const [workspaces, error] = await workspace.list()
   * if (error) {
   *   console.error('Failed to list workspaces:', error)
   * } else {
   *   console.log('Found', workspaces.length, 'workspaces')
   * }
   * ```
   */
  async list(): Promise<[WorkspaceData[] | null, any]> {
    const [response, error] =
      await this.apiClient.get<WorkspacesResponse>('/api/workspaces');

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Get a specific workspace by ID
   *
   * @param workspaceId - The workspace ID to retrieve
   * @returns Promise resolving to a tuple [WorkspaceData | null, any]
   *
   * @example
   * ```typescript
   * const [workspace, error] = await workspace.get('workspace-id')
   * if (error) {
   *   console.error('Failed to get workspace:', error)
   * } else {
   *   console.log('Workspace:', workspace.name)
   * }
   * ```
   */
  async get(workspaceId: string): Promise<[WorkspaceData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    const [response, error] = await this.apiClient.get<WorkspaceResponse>(
      `/api/workspaces/${workspaceId}`,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Update a workspace
   *
   * @param workspaceId - The workspace ID to update
   * @param data - Workspace update data
   * @returns Promise resolving to a tuple [WorkspaceData | null, any]
   *
   * @example
   * ```typescript
   * const [updated, error] = await workspace.update('workspace-id', {
   *   name: 'Updated Name',
   *   slug: 'updated-slug'
   * })
   * ```
   */
  async update(
    workspaceId: string,
    data: UpdateWorkspaceRequest,
  ): Promise<[WorkspaceData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!data || Object.keys(data).length === 0) {
      return [null, new Error('Update data is required')];
    }

    const [response, error] = await this.apiClient.put<WorkspaceResponse>(
      `/api/workspaces/${workspaceId}`,
      data,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Delete a workspace
   *
   * @param workspaceId - The workspace ID to delete
   * @returns Promise resolving to a tuple [boolean | null, any]
   *
   * @example
   * ```typescript
   * const [success, error] = await workspace.delete('workspace-id')
   * if (error) {
   *   console.error('Failed to delete workspace:', error)
   * } else {
   *   console.log('Workspace deleted successfully')
   * }
   * ```
   */
  async delete(workspaceId: string): Promise<[boolean | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    const [response, error] = await this.apiClient.delete(
      `/api/workspaces/${workspaceId}`,
    );

    if (error) {
      return [null, error];
    }

    return [true, null];
  }
}
