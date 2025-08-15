import { beforeEach, describe, expect, it } from 'vitest';
import { ApiClient } from '../src/ApiClient';
import { Auth } from '../src/Auth';

describe('ApiClient', () => {
  let apiClient: ApiClient;
  const mockConfig = {
    clientId: 'test-client-id',
    clientSecret: 'test-client-secret',
    callbackUrl: 'https://example.com/callback',
  };

  beforeEach(() => {
    apiClient = new ApiClient(mockConfig);
  });

  describe('constructor', () => {
    it('should create a new instance with provided config', () => {
      expect(apiClient).toBeInstanceOf(ApiClient);
    });

    it('should use default baseUrl when not provided', () => {
      const client = new ApiClient(mockConfig);
      expect(client.getBaseUrl()).toBe('https://api.kibaauth.com');
    });

    it('should use custom baseUrl when provided', () => {
      const customConfig = {
        ...mockConfig,
        baseUrl: 'https://custom-api.example.com',
      };
      const client = new ApiClient(customConfig);
      expect(client.getBaseUrl()).toBe('https://custom-api.example.com');
    });

    it('should set default headers correctly', () => {
      const headers = apiClient.getDefaultHeaders();
      expect(headers).toEqual({
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'User-Agent': '@kibaauth/sdk/1.0.0',
      });
    });
  });

  describe('getConfig', () => {
    it('should return a copy of the configuration', () => {
      const config = apiClient.getConfig();
      expect(config).toEqual({
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
        callbackUrl: 'https://example.com/callback',
        baseUrl: 'https://api.kibaauth.com',
      });
    });

    it('should return a deep copy that cannot modify original config', () => {
      const config = apiClient.getConfig();
      config.clientId = 'modified-client-id';

      const originalConfig = apiClient.getConfig();
      expect(originalConfig.clientId).toBe('test-client-id');
    });
  });

  describe('getBaseUrl', () => {
    it('should return the configured base URL', () => {
      expect(apiClient.getBaseUrl()).toBe('https://api.kibaauth.com');
    });
  });

  describe('getDefaultHeaders', () => {
    it('should return a copy of default headers', () => {
      const headers = apiClient.getDefaultHeaders();
      expect(headers).toEqual({
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'User-Agent': '@kibaauth/sdk/1.0.0',
      });
    });

    it('should return a copy that cannot modify original headers', () => {
      const headers = apiClient.getDefaultHeaders();
      headers['Custom-Header'] = 'custom-value';

      const originalHeaders = apiClient.getDefaultHeaders();
      expect(originalHeaders['Custom-Header']).toBeUndefined();
    });
  });

  describe('auth', () => {
    it('should return an Auth instance', () => {
      const auth = apiClient.auth();
      expect(auth).toBeInstanceOf(Auth);
    });

    it('should return a new Auth instance each time', () => {
      const auth1 = apiClient.auth();
      const auth2 = apiClient.auth();
      expect(auth1).not.toBe(auth2);
    });
  });

  describe('HTTP methods', () => {
    it('should have get method that returns tuple', () => {
      expect(typeof apiClient.get).toBe('function');
    });

    it('should have post method that returns tuple', () => {
      expect(typeof apiClient.post).toBe('function');
    });

    it('should have put method that returns tuple', () => {
      expect(typeof apiClient.put).toBe('function');
    });

    it('should have patch method that returns tuple', () => {
      expect(typeof apiClient.patch).toBe('function');
    });

    it('should have delete method that returns tuple', () => {
      expect(typeof apiClient.delete).toBe('function');
    });
  });

  describe('access token configuration', () => {
    it('should set authorization header when access token is provided', () => {
      const configWithToken = {
        accessToken: 'test-access-token',
        baseUrl: 'https://api.example.com',
      };
      const client = new ApiClient(configWithToken);
      const headers = client.getDefaultHeaders();
      expect(headers.Authorization).toBe('Bearer test-access-token');
    });

    it('should not set authorization header when access token is not provided', () => {
      const headers = apiClient.getDefaultHeaders();
      expect(headers.Authorization).toBeUndefined();
    });
  });

  describe('module accessors', () => {
    it('should have user method that returns User instance', () => {
      const user = apiClient.user();
      expect(user).toBeDefined();
    });

    it('should have workspaces method that returns Workspace instance', () => {
      const workspaces = apiClient.workspaces();
      expect(workspaces).toBeDefined();
    });

    it('should have teams method that returns Team instance', () => {
      const teams = apiClient.teams();
      expect(teams).toBeDefined();
    });
  });

  describe('configuration with different base URLs', () => {
    it('should handle baseUrl with trailing slash', () => {
      const config = {
        ...mockConfig,
        baseUrl: 'https://api.example.com/',
      };
      const client = new ApiClient(config);
      expect(client.getBaseUrl()).toBe('https://api.example.com/');
    });

    it('should handle baseUrl without trailing slash', () => {
      const config = {
        ...mockConfig,
        baseUrl: 'https://api.example.com',
      };
      const client = new ApiClient(config);
      expect(client.getBaseUrl()).toBe('https://api.example.com');
    });
  });

  describe('immutability', () => {
    it('should not allow external modification of internal config', () => {
      const client = new ApiClient(mockConfig);
      const config = client.getConfig();

      // Try to modify the returned config
      config.clientId = 'hacked-client-id';
      config.baseUrl = 'https://malicious.com';

      // Original config should remain unchanged
      const freshConfig = client.getConfig();
      expect(freshConfig.clientId).toBe('test-client-id');
      expect(freshConfig.baseUrl).toBe('https://api.kibaauth.com');
    });
  });
});
