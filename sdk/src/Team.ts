import type { ApiClient } from './ApiClient';
import type {
  CreateTeamMemberRequest,
  CreateTeamRequest,
  SyncPermissionsRequest,
  Team as TeamData,
  TeamMember as TeamMemberData,
  TeamMemberResponse,
  TeamResponse,
  TeamsResponse,
  UpdateTeamRequest,
} from './types';

/**
 * Team class for handling team-related API operations
 *
 * @example
 * ```typescript
 * const team = authenticatedClient.teams()
 *
 * // Create a team in a workspace
 * const [team, error] = await team.create('workspace-id', { name: 'My Team' })
 *
 * // List teams in a workspace
 * const [teams, error] = await team.list('workspace-id')
 *
 * // Get a specific team
 * const [team, error] = await team.get('workspace-id', 'team-id')
 *
 * // Update a team
 * const [updated, error] = await team.update('workspace-id', 'team-id', { name: 'New Name' })
 *
 * // Delete a team
 * const [success, error] = await team.delete('workspace-id', 'team-id')
 *
 * // Sync team permissions
 * const [team, error] = await team.syncPermissions('workspace-id', 'team-id', { permission_ids: ['perm1', 'perm2'] })
 *
 * // Add team member
 * const [member, error] = await team.addMember('workspace-id', 'team-id', { user_id: 'user-id' })
 *
 * // Remove team member
 * const [success, error] = await team.removeMember('workspace-id', 'team-id', 'member-id')
 *
 * // Accept invitation
 * const [member, error] = await team.acceptInvitation('workspace-id', 'team-id', 'member-id')
 *
 * // Reject invitation
 * const [success, error] = await team.rejectInvitation('workspace-id', 'team-id', 'member-id')
 * ```
 */
export class Team {
  private apiClient: ApiClient;

  /**
   * Create a new Team instance
   *
   * @param apiClient - The API client instance to use for requests
   */
  constructor(apiClient: ApiClient) {
    this.apiClient = apiClient;
  }

  /**
   * Create a new team in a workspace
   *
   * @param workspaceId - The workspace ID to create the team in
   * @param data - Team creation data
   * @returns Promise resolving to a tuple [TeamData | null, any]
   *
   * @example
   * ```typescript
   * const [team, error] = await team.create('workspace-id', {
   *   name: 'My Team',
   *   description: 'Team description',
   *   slug: 'my-team',
   *   permission_ids: ['perm1', 'perm2']
   * })
   * ```
   */
  async create(
    workspaceId: string,
    data: CreateTeamRequest,
  ): Promise<[TeamData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!data.name || !data.name.trim()) {
      return [null, new Error('Team name is required')];
    }

    const [response, error] = await this.apiClient.post<TeamResponse>(
      `/api/workspaces/${workspaceId}/teams`,
      data,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * List all teams in a workspace
   *
   * @param workspaceId - The workspace ID to list teams from
   * @returns Promise resolving to a tuple [TeamData[] | null, any]
   *
   * @example
   * ```typescript
   * const [teams, error] = await team.list('workspace-id')
   * ```
   */
  async list(workspaceId: string): Promise<[TeamData[] | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    const [response, error] = await this.apiClient.get<TeamsResponse>(
      `/api/workspaces/${workspaceId}/teams`,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Get a specific team by ID
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID to retrieve
   * @returns Promise resolving to a tuple [TeamData | null, any]
   *
   * @example
   * ```typescript
   * const [team, error] = await team.get('workspace-id', 'team-id')
   * ```
   */
  async get(
    workspaceId: string,
    teamId: string,
  ): Promise<[TeamData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    const [response, error] = await this.apiClient.get<TeamResponse>(
      `/api/workspaces/${workspaceId}/teams/${teamId}`,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Update a team
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID to update
   * @param data - Team update data
   * @returns Promise resolving to a tuple [TeamData | null, any]
   *
   * @example
   * ```typescript
   * const [updated, error] = await team.update('workspace-id', 'team-id', {
   *   name: 'Updated Name',
   *   description: 'Updated description'
   * })
   * ```
   */
  async update(
    workspaceId: string,
    teamId: string,
    data: UpdateTeamRequest,
  ): Promise<[TeamData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    if (!data || Object.keys(data).length === 0) {
      return [null, new Error('Update data is required')];
    }

    const [response, error] = await this.apiClient.put<TeamResponse>(
      `/api/workspaces/${workspaceId}/teams/${teamId}`,
      data,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Delete a team
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID to delete
   * @returns Promise resolving to a tuple [boolean | null, any]
   *
   * @example
   * ```typescript
   * const [success, error] = await team.delete('workspace-id', 'team-id')
   * ```
   */
  async delete(
    workspaceId: string,
    teamId: string,
  ): Promise<[boolean | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    const [response, error] = await this.apiClient.delete(
      `/api/workspaces/${workspaceId}/teams/${teamId}`,
    );

    if (error) {
      return [null, error];
    }

    return [true, null];
  }

  /**
   * Sync team permissions
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID
   * @param data - Permissions sync data
   * @returns Promise resolving to a tuple [TeamData | null, any]
   *
   * @example
   * ```typescript
   * const [team, error] = await team.syncPermissions('workspace-id', 'team-id', {
   *   permission_ids: ['perm1', 'perm2', 'perm3']
   * })
   * ```
   */
  async syncPermissions(
    workspaceId: string,
    teamId: string,
    data: SyncPermissionsRequest,
  ): Promise<[TeamData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    if (!data.permission_ids || !Array.isArray(data.permission_ids)) {
      return [null, new Error('Permission IDs array is required')];
    }

    const [response, error] = await this.apiClient.post<TeamResponse>(
      `/api/workspaces/${workspaceId}/teams/${teamId}/sync-permissions`,
      data,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Add a member to a team
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID
   * @param data - Team member creation data
   * @returns Promise resolving to a tuple [TeamMemberData | null, any]
   *
   * @example
   * ```typescript
   * // Add existing user
   * const [member, error] = await team.addMember('workspace-id', 'team-id', {
   *   user_id: 'user-id',
   *   status: 'active'
   * })
   *
   * // Invite by email
   * const [member, error] = await team.addMember('workspace-id', 'team-id', {
   *   email: 'user@example.com',
   *   status: 'pending'
   * })
   * ```
   */
  async addMember(
    workspaceId: string,
    teamId: string,
    data: CreateTeamMemberRequest,
  ): Promise<[TeamMemberData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    if (!data.user_id && !data.email) {
      return [null, new Error('Either user_id or email must be provided')];
    }

    if (data.user_id && data.email) {
      return [null, new Error('Cannot provide both user_id and email')];
    }

    const [response, error] = await this.apiClient.post<TeamMemberResponse>(
      `/api/workspaces/${workspaceId}/teams/${teamId}/members`,
      data,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Remove a member from a team
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID
   * @param memberId - The team member ID to remove
   * @returns Promise resolving to a tuple [boolean | null, any]
   *
   * @example
   * ```typescript
   * const [success, error] = await team.removeMember('workspace-id', 'team-id', 'member-id')
   * ```
   */
  async removeMember(
    workspaceId: string,
    teamId: string,
    memberId: string,
  ): Promise<[boolean | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    if (!memberId || !memberId.trim()) {
      return [null, new Error('Member ID is required')];
    }

    const [response, error] = await this.apiClient.delete(
      `/api/workspaces/${workspaceId}/teams/${teamId}/members/${memberId}`,
    );

    if (error) {
      return [null, error];
    }

    return [true, null];
  }

  /**
   * Accept a team invitation
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID
   * @param memberId - The team member ID to accept invitation for
   * @returns Promise resolving to a tuple [TeamMemberData | null, any]
   *
   * @example
   * ```typescript
   * const [member, error] = await team.acceptInvitation('workspace-id', 'team-id', 'member-id')
   * ```
   */
  async acceptInvitation(
    workspaceId: string,
    teamId: string,
    memberId: string,
  ): Promise<[TeamMemberData | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    if (!memberId || !memberId.trim()) {
      return [null, new Error('Member ID is required')];
    }

    const [response, error] = await this.apiClient.patch<TeamMemberResponse>(
      `/api/workspaces/${workspaceId}/teams/${teamId}/members/${memberId}/accept`,
    );

    if (error) {
      return [null, error];
    }

    return [response?.data || null, null];
  }

  /**
   * Reject a team invitation
   *
   * @param workspaceId - The workspace ID
   * @param teamId - The team ID
   * @param memberId - The team member ID to reject invitation for
   * @returns Promise resolving to a tuple [boolean | null, any]
   *
   * @example
   * ```typescript
   * const [success, error] = await team.rejectInvitation('workspace-id', 'team-id', 'member-id')
   * ```
   */
  async rejectInvitation(
    workspaceId: string,
    teamId: string,
    memberId: string,
  ): Promise<[boolean | null, any]> {
    if (!workspaceId || !workspaceId.trim()) {
      return [null, new Error('Workspace ID is required')];
    }

    if (!teamId || !teamId.trim()) {
      return [null, new Error('Team ID is required')];
    }

    if (!memberId || !memberId.trim()) {
      return [null, new Error('Member ID is required')];
    }

    const [response, error] = await this.apiClient.delete(
      `/api/workspaces/${workspaceId}/teams/${teamId}/members/${memberId}/reject`,
    );

    if (error) {
      return [null, error];
    }

    return [true, null];
  }
}
