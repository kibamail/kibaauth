import type { ApiClient } from './ApiClient';

/**
 * Response interface for access token requests
 */
export interface AccessTokenResponse {
  /** The access token for authenticated requests */
  access_token: string;
  /** The type of token (usually 'Bearer') */
  token_type: string;
  /** Token expiration time in seconds */
  expires_in: number;
  /** Refresh token for obtaining new access tokens */
  refresh_token?: string;
  /** The scope of access granted by the token */
  scope?: string;
}

/**
 * Response interface for refresh token requests
 */
export interface RefreshTokenResponse extends AccessTokenResponse {}

/**
 * Authorization URL parameters
 */
export interface AuthorizationUrlParams {
  /** OAuth2 scopes to request */
  scopes?: string[];
  /** State parameter for CSRF protection */
  state?: string;
  /** Additional query parameters */
  additionalParams?: Record<string, string>;
}

/**
 * Auth class for handling OAuth2 authentication flows
 *
 * @example
 * ```typescript
 * const auth = apiClient.auth()
 *
 * // Exchange authorization code for access token
 * const tokenResponse = await auth.accessToken('authorization-code-here')
 *
 * // Refresh an existing token
 * const refreshedToken = await auth.refreshToken('refresh-token-here')
 *
 * // Generate authorization URL
 * const authUrl = auth.authorizationUrl({ scopes: ['read', 'write'] })
 * ```
 */
export class Auth {
  private apiClient: ApiClient;

  /**
   * Create a new Auth instance
   *
   * @param apiClient - The API client instance to use for requests
   */
  constructor(apiClient: ApiClient) {
    this.apiClient = apiClient;
  }

  /**
   * Exchange an authorization code for an access token
   *
   * @param code - The authorization code received from the OAuth2 callback
   * @returns Promise resolving to the access token response
   *
   * @example
   * ```typescript
   * const response = await auth.accessToken('auth-code-123')
   * console.log(response.access_token) // Use this token for authenticated requests
   * ```
   */
  async accessToken(code: string): Promise<[AccessTokenResponse | null, any]> {
    if (!code || !code.trim()) {
      return [null, new Error('Authorization code is required')];
    }

    const config = this.apiClient.getConfig();

    const [response, error] = await this.apiClient.post<AccessTokenResponse>(
      '/oauth/token',
      {
        grant_type: 'authorization_code',
        client_id: config.clientId,
        client_secret: config.clientSecret,
        redirect_uri: config.callbackUrl,
        code: code.trim(),
      },
    );

    return [response, error];
  }

  /**
   * Refresh an existing access token using a refresh token
   *
   * @param refreshToken - The refresh token to use for obtaining a new access token
   * @returns Promise resolving to the new access token response
   *
   * @example
   * ```typescript
   * const response = await auth.refreshToken('refresh-token-123')
   * console.log(response.access_token) // New access token
   * ```
   */
  async refreshToken(
    refreshToken: string,
  ): Promise<[RefreshTokenResponse | null, any]> {
    if (!refreshToken || !refreshToken.trim()) {
      return [null, new Error('Refresh token is required')];
    }

    const config = this.apiClient.getConfig();

    const [response, error] = await this.apiClient.post<RefreshTokenResponse>(
      '/oauth/token',
      {
        grant_type: 'refresh_token',
        client_id: config.clientId,
        client_secret: config.clientSecret,
        refresh_token: refreshToken.trim(),
      },
    );

    return [response, error];
  }

  /**
   * Generate an authorization URL for initiating the OAuth2 flow
   *
   * @param params - Parameters for customizing the authorization URL
   * @returns The complete authorization URL for redirecting users
   *
   * @example
   * ```typescript
   * const authUrl = auth.authorizationUrl({
   *   scopes: ['read', 'write'],
   *   state: 'random-state-value'
   * })
   * // Redirect user to authUrl
   * ```
   */
  authorizationUrl(params: AuthorizationUrlParams = {}): string {
    const config = this.apiClient.getConfig();
    const baseUrl = this.apiClient.getBaseUrl();

    const queryParams = new URLSearchParams({
      response_type: 'code',
      client_id: config.clientId || '',
      redirect_uri: config.callbackUrl || '',
      ...params.additionalParams,
    });

    if (params.scopes && params.scopes.length > 0) {
      queryParams.set('scope', params.scopes.join(' '));
    }

    if (params.state) {
      queryParams.set('state', params.state);
    }

    return `${baseUrl}/oauth/authorize?${queryParams.toString()}`;
  }

  /**
   * Revoke an access token
   *
   * @param token - The access token to revoke
   * @returns Promise resolving when the token is successfully revoked
   *
   * @example
   * ```typescript
   * await auth.revokeToken('access-token-123')
   * console.log('Token revoked successfully')
   * ```
   */
  async revokeToken(token: string): Promise<[boolean | null, any]> {
    if (!token || !token.trim()) {
      return [null, new Error('Token is required for revocation')];
    }

    const [response, error] = await this.apiClient.post('/oauth/revoke', {
      token: token.trim(),
    });

    if (error) {
      return [null, error];
    }

    return [true, null];
  }

  /**
   * Validate an access token
   *
   * @param token - The access token to validate
   * @returns Promise resolving to token information if valid
   *
   * @example
   * ```typescript
   * const tokenInfo = await auth.validateToken('access-token-123')
   * console.log(tokenInfo.expires_in) // Time until expiration
   * ```
   */
  async validateToken(token: string): Promise<[any | null, any]> {
    if (!token || !token.trim()) {
      return [null, new Error('Token is required for validation')];
    }

    const [response, error] = await this.apiClient.get(
      `/oauth/introspect?token=${encodeURIComponent(token.trim())}`,
    );

    return [response, error];
  }
}
