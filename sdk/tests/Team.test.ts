import { describe, it, expect, vi, beforeEach } from 'vitest';
import { Team, type TeamData, type TeamMemberData } from '../src/Team';
import type { ApiClient } from '../src/ApiClient';

// Mock the ApiClient
const mockApiClient = {
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
} as unknown as ApiClient;

describe('Team', () => {
  let team: Team;

  beforeEach(() => {
    team = new Team(mockApiClient);
    vi.clearAllMocks();
  });

  describe('create', () => {
    const mockTeamData: TeamData = {
      id: 'team-123',
      name: 'Test Team',
      description: 'A test team',
      slug: 'test-team',
      workspace_id: 'workspace-123',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-01T00:00:00.000Z',
      permissions: [],
    };

    it('should create a team successfully', async () => {
      const mockResponse = { data: mockTeamData, message: 'Team created successfully' };
      vi.mocked(mockApiClient.post).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.create('workspace-123', {
        name: 'Test Team',
        description: 'A test team',
        slug: 'test-team',
        permission_ids: ['perm-1', 'perm-2'],
      });

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamData);
      expect(mockApiClient.post).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams',
        {
          name: 'Test Team',
          description: 'A test team',
          slug: 'test-team',
          permission_ids: ['perm-1', 'perm-2'],
        }
      );
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.create('', { name: 'Test Team' });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should return error when team name is missing', async () => {
      const [result, error] = await team.create('workspace-123', { name: '' });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team name is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should handle API errors', async () => {
      const mockError = new Error('API Error');
      vi.mocked(mockApiClient.post).mockResolvedValueOnce([null, mockError]);

      const [result, error] = await team.create('workspace-123', { name: 'Test Team' });

      expect(result).toBeNull();
      expect(error).toBe(mockError);
    });

    it('should handle null response data', async () => {
      vi.mocked(mockApiClient.post).mockResolvedValueOnce([null, null]);

      const [result, error] = await team.create('workspace-123', { name: 'Test Team' });

      expect(result).toBeNull();
      expect(error).toBeNull();
    });
  });

  describe('list', () => {
    const mockTeamsData: TeamData[] = [
      {
        id: 'team-1',
        name: 'Team One',
        slug: 'team-one',
        workspace_id: 'workspace-123',
        created_at: '2023-01-01T00:00:00.000Z',
        updated_at: '2023-01-01T00:00:00.000Z',
        permissions: [],
      },
      {
        id: 'team-2',
        name: 'Team Two',
        slug: 'team-two',
        workspace_id: 'workspace-123',
        created_at: '2023-01-01T00:00:00.000Z',
        updated_at: '2023-01-01T00:00:00.000Z',
        permissions: [],
      },
    ];

    it('should list teams successfully', async () => {
      const mockResponse = { data: mockTeamsData };
      vi.mocked(mockApiClient.get).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.list('workspace-123');

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamsData);
      expect(mockApiClient.get).toHaveBeenCalledWith('/api/workspaces/workspace-123/teams');
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.list('');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.get).not.toHaveBeenCalled();
    });

    it('should handle API errors', async () => {
      const mockError = new Error('API Error');
      vi.mocked(mockApiClient.get).mockResolvedValueOnce([null, mockError]);

      const [result, error] = await team.list('workspace-123');

      expect(result).toBeNull();
      expect(error).toBe(mockError);
    });
  });

  describe('get', () => {
    const mockTeamData: TeamData = {
      id: 'team-123',
      name: 'Test Team',
      description: 'A test team',
      slug: 'test-team',
      workspace_id: 'workspace-123',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-01T00:00:00.000Z',
      permissions: [],
    };

    it('should get a team successfully', async () => {
      const mockResponse = { data: mockTeamData };
      vi.mocked(mockApiClient.get).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.get('workspace-123', 'team-123');

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamData);
      expect(mockApiClient.get).toHaveBeenCalledWith('/api/workspaces/workspace-123/teams/team-123');
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.get('', 'team-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.get).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.get('workspace-123', '');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.get).not.toHaveBeenCalled();
    });
  });

  describe('update', () => {
    const mockTeamData: TeamData = {
      id: 'team-123',
      name: 'Updated Team',
      description: 'Updated description',
      slug: 'updated-team',
      workspace_id: 'workspace-123',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-02T00:00:00.000Z',
      permissions: [],
    };

    it('should update a team successfully', async () => {
      const mockResponse = { data: mockTeamData, message: 'Team updated successfully' };
      vi.mocked(mockApiClient.put).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.update('workspace-123', 'team-123', {
        name: 'Updated Team',
        description: 'Updated description',
      });

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamData);
      expect(mockApiClient.put).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123',
        {
          name: 'Updated Team',
          description: 'Updated description',
        }
      );
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.update('', 'team-123', { name: 'Updated' });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.put).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.update('workspace-123', '', { name: 'Updated' });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.put).not.toHaveBeenCalled();
    });

    it('should return error when update data is empty', async () => {
      const [result, error] = await team.update('workspace-123', 'team-123', {});

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Update data is required');
      expect(mockApiClient.put).not.toHaveBeenCalled();
    });
  });

  describe('delete', () => {
    it('should delete a team successfully', async () => {
      const mockResponse = { message: 'Team deleted successfully' };
      vi.mocked(mockApiClient.delete).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.delete('workspace-123', 'team-123');

      expect(error).toBeNull();
      expect(result).toBe(true);
      expect(mockApiClient.delete).toHaveBeenCalledWith('/api/workspaces/workspace-123/teams/team-123');
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.delete('', 'team-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.delete('workspace-123', '');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });
  });

  describe('syncPermissions', () => {
    const mockTeamData: TeamData = {
      id: 'team-123',
      name: 'Test Team',
      slug: 'test-team',
      workspace_id: 'workspace-123',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-01T00:00:00.000Z',
      permissions: [
        {
          id: 'perm-1',
          name: 'teams:create',
          client_id: 'client-123',
          created_at: '2023-01-01T00:00:00.000Z',
          updated_at: '2023-01-01T00:00:00.000Z',
        },
      ],
    };

    it('should sync permissions successfully', async () => {
      const mockResponse = { data: mockTeamData, message: 'Permissions synced successfully' };
      vi.mocked(mockApiClient.post).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.syncPermissions('workspace-123', 'team-123', {
        permission_ids: ['perm-1', 'perm-2'],
      });

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamData);
      expect(mockApiClient.post).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123/sync-permissions',
        { permission_ids: ['perm-1', 'perm-2'] }
      );
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.syncPermissions('', 'team-123', { permission_ids: ['perm-1'] });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.syncPermissions('workspace-123', '', { permission_ids: ['perm-1'] });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should return error when permission_ids is not an array', async () => {
      const [result, error] = await team.syncPermissions('workspace-123', 'team-123', {
        permission_ids: 'invalid' as any,
      });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Permission IDs array is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });
  });

  describe('addMember', () => {
    const mockTeamMemberData: TeamMemberData = {
      id: 'member-123',
      team_id: 'team-123',
      user_id: 'user-123',
      email: null,
      status: 'active',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-01T00:00:00.000Z',
      user: {
        id: 'user-123',
        name: 'John Doe',
        email: 'john@example.com',
      },
    };

    it('should add a member by user_id successfully', async () => {
      const mockResponse = { data: mockTeamMemberData, message: 'Member added successfully' };
      vi.mocked(mockApiClient.post).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.addMember('workspace-123', 'team-123', {
        user_id: 'user-123',
        status: 'active',
      });

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamMemberData);
      expect(mockApiClient.post).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123/members',
        { user_id: 'user-123', status: 'active' }
      );
    });

    it('should add a member by email successfully', async () => {
      const emailMemberData = {
        ...mockTeamMemberData,
        user_id: null,
        email: 'invite@example.com',
        status: 'pending' as const,
        user: undefined,
      };
      const mockResponse = { data: emailMemberData, message: 'Invitation sent successfully' };
      vi.mocked(mockApiClient.post).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.addMember('workspace-123', 'team-123', {
        email: 'invite@example.com',
        status: 'pending',
      });

      expect(error).toBeNull();
      expect(result).toEqual(emailMemberData);
      expect(mockApiClient.post).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123/members',
        { email: 'invite@example.com', status: 'pending' }
      );
    });

    it('should return error when neither user_id nor email is provided', async () => {
      const [result, error] = await team.addMember('workspace-123', 'team-123', {});

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Either user_id or email must be provided');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should return error when both user_id and email are provided', async () => {
      const [result, error] = await team.addMember('workspace-123', 'team-123', {
        user_id: 'user-123',
        email: 'test@example.com',
      });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Cannot provide both user_id and email');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.addMember('', 'team-123', { user_id: 'user-123' });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.addMember('workspace-123', '', { user_id: 'user-123' });

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.post).not.toHaveBeenCalled();
    });
  });

  describe('removeMember', () => {
    it('should remove a member successfully', async () => {
      const mockResponse = { message: 'Member removed successfully' };
      vi.mocked(mockApiClient.delete).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.removeMember('workspace-123', 'team-123', 'member-123');

      expect(error).toBeNull();
      expect(result).toBe(true);
      expect(mockApiClient.delete).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123/members/member-123'
      );
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.removeMember('', 'team-123', 'member-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.removeMember('workspace-123', '', 'member-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });

    it('should return error when member ID is missing', async () => {
      const [result, error] = await team.removeMember('workspace-123', 'team-123', '');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Member ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });
  });

  describe('acceptInvitation', () => {
    const mockTeamMemberData: TeamMemberData = {
      id: 'member-123',
      team_id: 'team-123',
      user_id: 'user-123',
      email: null,
      status: 'active',
      created_at: '2023-01-01T00:00:00.000Z',
      updated_at: '2023-01-01T00:00:00.000Z',
      user: {
        id: 'user-123',
        name: 'John Doe',
        email: 'john@example.com',
      },
    };

    it('should accept invitation successfully', async () => {
      const mockResponse = { data: mockTeamMemberData, message: 'Invitation accepted successfully' };
      vi.mocked(mockApiClient.patch).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.acceptInvitation('workspace-123', 'team-123', 'member-123');

      expect(error).toBeNull();
      expect(result).toEqual(mockTeamMemberData);
      expect(mockApiClient.patch).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123/members/member-123/accept'
      );
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.acceptInvitation('', 'team-123', 'member-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.patch).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.acceptInvitation('workspace-123', '', 'member-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.patch).not.toHaveBeenCalled();
    });

    it('should return error when member ID is missing', async () => {
      const [result, error] = await team.acceptInvitation('workspace-123', 'team-123', '');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Member ID is required');
      expect(mockApiClient.patch).not.toHaveBeenCalled();
    });
  });

  describe('rejectInvitation', () => {
    it('should reject invitation successfully', async () => {
      const mockResponse = { message: 'Invitation rejected successfully' };
      vi.mocked(mockApiClient.delete).mockResolvedValueOnce([mockResponse, null]);

      const [result, error] = await team.rejectInvitation('workspace-123', 'team-123', 'member-123');

      expect(error).toBeNull();
      expect(result).toBe(true);
      expect(mockApiClient.delete).toHaveBeenCalledWith(
        '/api/workspaces/workspace-123/teams/team-123/members/member-123/reject'
      );
    });

    it('should return error when workspace ID is missing', async () => {
      const [result, error] = await team.rejectInvitation('', 'team-123', 'member-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Workspace ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });

    it('should return error when team ID is missing', async () => {
      const [result, error] = await team.rejectInvitation('workspace-123', '', 'member-123');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Team ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });

    it('should return error when member ID is missing', async () => {
      const [result, error] = await team.rejectInvitation('workspace-123', 'team-123', '');

      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Member ID is required');
      expect(mockApiClient.delete).not.toHaveBeenCalled();
    });
  });
});
