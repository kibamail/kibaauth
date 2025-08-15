import { beforeEach, describe, expect, it } from 'vitest';
import { ApiClient } from '../src/ApiClient';
import { Auth } from '../src/Auth';

describe('Auth', () => {
  let auth: Auth;
  let mockApiClient: ApiClient;
  const mockConfig = {
    clientId: 'test-client-id',
    clientSecret: 'test-client-secret',
    callbackUrl: 'https://example.com/callback',
  };

  beforeEach(() => {
    mockApiClient = new ApiClient(mockConfig);
    auth = new Auth(mockApiClient);
  });

  describe('constructor', () => {
    it('should create a new instance with ApiClient', () => {
      expect(auth).toBeInstanceOf(Auth);
    });
  });

  describe('accessToken', () => {
    it('should return error tuple when code is not provided', async () => {
      const [result, error] = await auth.accessToken('');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Authorization code is required');
    });

    it('should return error tuple for null/undefined code', async () => {
      const [result1, error1] = await auth.accessToken(null as any);
      expect(result1).toBeNull();
      expect(error1).toBeInstanceOf(Error);
      expect(error1.message).toBe('Authorization code is required');

      const [result2, error2] = await auth.accessToken(undefined as any);
      expect(result2).toBeNull();
      expect(error2).toBeInstanceOf(Error);
      expect(error2.message).toBe('Authorization code is required');
    });

    it('should make API call when valid code is provided', async () => {
      const [result, error] = await auth.accessToken('valid-auth-code');
      expect(result).toBeNull();
      expect(error).toBeDefined(); // Will be a network error in tests
    });
  });

  describe('refreshToken', () => {
    it('should return error tuple when refresh token is not provided', async () => {
      const [result, error] = await auth.refreshToken('');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Refresh token is required');
    });

    it('should return error tuple for null/undefined refresh token', async () => {
      const [result1, error1] = await auth.refreshToken(null as any);
      expect(result1).toBeNull();
      expect(error1).toBeInstanceOf(Error);
      expect(error1.message).toBe('Refresh token is required');

      const [result2, error2] = await auth.refreshToken(undefined as any);
      expect(result2).toBeNull();
      expect(error2).toBeInstanceOf(Error);
      expect(error2.message).toBe('Refresh token is required');
    });

    it('should make API call when valid refresh token is provided', async () => {
      const [result, error] = await auth.refreshToken('valid-refresh-token');
      expect(result).toBeNull();
      expect(error).toBeDefined(); // Will be a network error in tests
    });
  });

  describe('authorizationUrl', () => {
    it('should generate basic authorization URL with minimal config', () => {
      const url = auth.authorizationUrl();
      const expectedUrl = new URL(url);

      expect(expectedUrl.origin).toBe('https://api.kibaauth.com');
      expect(expectedUrl.pathname).toBe('/oauth/authorize');
      expect(expectedUrl.searchParams.get('response_type')).toBe('code');
      expect(expectedUrl.searchParams.get('client_id')).toBe('test-client-id');
      expect(expectedUrl.searchParams.get('redirect_uri')).toBe(
        'https://example.com/callback',
      );
    });

    it('should include scopes in authorization URL when provided', () => {
      const url = auth.authorizationUrl({ scopes: ['read', 'write', 'admin'] });
      const expectedUrl = new URL(url);

      expect(expectedUrl.searchParams.get('scope')).toBe('read write admin');
    });

    it('should include state parameter when provided', () => {
      const url = auth.authorizationUrl({ state: 'random-state-value' });
      const expectedUrl = new URL(url);

      expect(expectedUrl.searchParams.get('state')).toBe('random-state-value');
    });

    it('should include additional parameters when provided', () => {
      const url = auth.authorizationUrl({
        additionalParams: {
          custom_param: 'custom_value',
          another_param: 'another_value',
        },
      });
      const expectedUrl = new URL(url);

      expect(expectedUrl.searchParams.get('custom_param')).toBe('custom_value');
      expect(expectedUrl.searchParams.get('another_param')).toBe(
        'another_value',
      );
    });

    it('should include all parameters when provided together', () => {
      const url = auth.authorizationUrl({
        scopes: ['read', 'write'],
        state: 'secure-state',
        additionalParams: {
          prompt: 'consent',
          access_type: 'offline',
        },
      });
      const expectedUrl = new URL(url);

      expect(expectedUrl.searchParams.get('scope')).toBe('read write');
      expect(expectedUrl.searchParams.get('state')).toBe('secure-state');
      expect(expectedUrl.searchParams.get('prompt')).toBe('consent');
      expect(expectedUrl.searchParams.get('access_type')).toBe('offline');
    });

    it('should handle empty scopes array', () => {
      const url = auth.authorizationUrl({ scopes: [] });
      const expectedUrl = new URL(url);

      expect(expectedUrl.searchParams.get('scope')).toBeNull();
    });

    it('should use custom base URL from ApiClient', () => {
      const customApiClient = new ApiClient({
        ...mockConfig,
        baseUrl: 'https://custom-api.example.com',
      });
      const customAuth = new Auth(customApiClient);

      const url = customAuth.authorizationUrl();
      const expectedUrl = new URL(url);

      expect(expectedUrl.origin).toBe('https://custom-api.example.com');
    });
  });

  describe('revokeToken', () => {
    it('should return error tuple when token is not provided', async () => {
      const [result, error] = await auth.revokeToken('');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Token is required for revocation');
    });

    it('should return error tuple for null/undefined token', async () => {
      const [result1, error1] = await auth.revokeToken(null as any);
      expect(result1).toBeNull();
      expect(error1).toBeInstanceOf(Error);
      expect(error1.message).toBe('Token is required for revocation');

      const [result2, error2] = await auth.revokeToken(undefined as any);
      expect(result2).toBeNull();
      expect(error2).toBeInstanceOf(Error);
      expect(error2.message).toBe('Token is required for revocation');
    });

    it('should make API call when valid token is provided', async () => {
      const [result, error] = await auth.revokeToken('valid-token');
      expect(result).toBeNull();
      expect(error).toBeDefined(); // Will be a network error in tests
    });
  });

  describe('validateToken', () => {
    it('should return error tuple when token is not provided', async () => {
      const [result, error] = await auth.validateToken('');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Token is required for validation');
    });

    it('should return error tuple for null/undefined token', async () => {
      const [result1, error1] = await auth.validateToken(null as any);
      expect(result1).toBeNull();
      expect(error1).toBeInstanceOf(Error);
      expect(error1.message).toBe('Token is required for validation');

      const [result2, error2] = await auth.validateToken(undefined as any);
      expect(result2).toBeNull();
      expect(error2).toBeInstanceOf(Error);
      expect(error2.message).toBe('Token is required for validation');
    });

    it('should make API call when valid token is provided', async () => {
      const [result, error] = await auth.validateToken('valid-token');
      expect(result).toBeNull();
      expect(error).toBeDefined(); // Will be a network error in tests
    });
  });

  describe('parameter validation edge cases', () => {
    it('should handle whitespace-only strings as empty for accessToken', async () => {
      const [result, error] = await auth.accessToken('   ');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Authorization code is required');
    });

    it('should handle whitespace-only strings as empty for refreshToken', async () => {
      const [result, error] = await auth.refreshToken('   ');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Refresh token is required');
    });

    it('should handle whitespace-only strings as empty for revokeToken', async () => {
      const [result, error] = await auth.revokeToken('   ');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Token is required for revocation');
    });

    it('should handle whitespace-only strings as empty for validateToken', async () => {
      const [result, error] = await auth.validateToken('   ');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Token is required for validation');
    });
  });

  describe('URL encoding in authorizationUrl', () => {
    it('should properly encode special characters in parameters', () => {
      const url = auth.authorizationUrl({
        state: 'state with spaces & special chars',
        additionalParams: {
          param: 'value with spaces & ampersands',
        },
      });

      // URLSearchParams uses + for spaces, which is valid URL encoding
      expect(url).toContain('state=state+with+spaces+%26+special+chars');
      expect(url).toContain('param=value+with+spaces+%26+ampersands');
    });

    it('should handle Unicode characters in scopes', () => {
      const url = auth.authorizationUrl({
        scopes: ['read:données', 'write:файлы'],
      });
      const expectedUrl = new URL(url);

      expect(expectedUrl.searchParams.get('scope')).toBe(
        'read:données write:файлы',
      );
    });
  });

  describe('integration with ApiClient configuration', () => {
    it('should use correct client configuration for authorization URL', () => {
      const customConfig = {
        clientId: 'custom-client-id',
        clientSecret: 'custom-client-secret',
        callbackUrl: 'https://custom-app.com/auth/callback',
        baseUrl: 'https://auth.custom-domain.com',
      };

      const customApiClient = new ApiClient(customConfig);
      const customAuth = new Auth(customApiClient);

      const url = customAuth.authorizationUrl({ state: 'test-state' });
      const expectedUrl = new URL(url);

      expect(expectedUrl.origin).toBe('https://auth.custom-domain.com');
      expect(expectedUrl.searchParams.get('client_id')).toBe(
        'custom-client-id',
      );
      expect(expectedUrl.searchParams.get('redirect_uri')).toBe(
        'https://custom-app.com/auth/callback',
      );
      expect(expectedUrl.searchParams.get('state')).toBe('test-state');
    });
  });
});
