import type { ApiClient } from './ApiClient';
import type { UserProfile } from './types';

/**
 * User class for handling user-related API operations
 *
 * @example
 * ```typescript
 * const user = apiClient.user()
 *
 * // Get current user profile
 * const [profile, error] = await user.profile()
 *
 * // Update user profile
 * const [updated, error] = await user.update({ email: 'new@example.com' })
 * ```
 */
export class User {
  private apiClient: ApiClient;

  /**
   * Create a new User instance
   *
   * @param apiClient - The API client instance to use for requests
   */
  constructor(apiClient: ApiClient) {
    this.apiClient = apiClient;
  }

  /**
   * Get the current user's profile information
   *
   * @returns Promise resolving to a tuple [UserProfile | null, any]
   *
   * @example
   * ```typescript
   * const [profile, error] = await user.profile()
   * if (error) {
   *   console.error('Failed to get profile:', error)
   * } else {
   *   console.log('User:', profile.email)
   *   console.log('Workspaces:', profile.workspaces.length)
   *   console.log('Pending invitations:', profile.pending_invitations.length)
   * }
   * ```
   */
  async profile(): Promise<[UserProfile | null, any]> {
    const [response, error] =
      await this.apiClient.get<UserProfile>('/api/user');

    return [response, error];
  }

  /**
   * Update the current user's profile information
   *
   * @param updates - User profile data to update (email only)
   * @returns Promise resolving to a tuple [UserProfile | null, any]
   *
   * @example
   * ```typescript
   * const [updated, error] = await user.update({
   *   email: 'newemail@example.com'
   * })
   * ```
   */
  async update(
    updates: Partial<Pick<UserProfile, 'email'>>,
  ): Promise<[UserProfile | null, any]> {
    if (!updates || Object.keys(updates).length === 0) {
      return [null, new Error('Updates are required')];
    }

    const [response, error] = await this.apiClient.put<UserProfile>(
      '/api/user',
      updates,
    );

    return [response, error];
  }

  /**
   * Delete the current user's account
   *
   * @returns Promise resolving to a tuple [boolean | null, any]
   *
   * @example
   * ```typescript
   * const [success, error] = await user.delete()
   * if (error) {
   *   console.error('Failed to delete account:', error)
   * } else {
   *   console.log('Account deleted successfully')
   * }
   * ```
   */
  async delete(): Promise<[boolean | null, any]> {
    const [response, error] = await this.apiClient.delete('/api/user');

    if (error) {
      return [null, error];
    }

    return [true, null];
  }
}
