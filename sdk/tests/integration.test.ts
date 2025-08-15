import { describe, expect, it } from 'vitest';
import { ApiClient } from '../src/ApiClient';
import { Auth } from '../src/Auth';
import { Kibaauth } from '../src/index';

describe('Integration Tests', () => {
  const testConfig = {
    clientId: 'test-client-id',
    clientSecret: 'test-client-secret',
    callbackUrl: 'https://example.com/callback',
  };

  describe('Complete API Flow', () => {
    it('should create API client using fluent interface', () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      expect(api).toBeInstanceOf(ApiClient);
    });

    it('should create API client with custom base URL', () => {
      const customBaseUrl = 'https://staging-api.kibaauth.com';
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .baseUrl(customBaseUrl)
        .api();

      expect(api.getBaseUrl()).toBe(customBaseUrl);
    });

    it('should provide auth module from API client', () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      const auth = api.auth();
      expect(auth).toBeInstanceOf(Auth);
    });

    it('should generate authorization URL through complete chain', () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      const authUrl = api.auth().authorizationUrl({
        scopes: ['read', 'write'],
        state: 'test-state',
      });

      const url = new URL(authUrl);
      expect(url.searchParams.get('client_id')).toBe(testConfig.clientId);
      expect(url.searchParams.get('redirect_uri')).toBe(testConfig.callbackUrl);
      expect(url.searchParams.get('scope')).toBe('read write');
      expect(url.searchParams.get('state')).toBe('test-state');
      expect(url.searchParams.get('response_type')).toBe('code');
    });

    it('should maintain configuration consistency across method calls', () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      const config1 = api.getConfig();
      const config2 = api.getConfig();

      expect(config1).toEqual(config2);
      expect(config1).not.toBe(config2); // Should be different objects (deep copy)
    });
  });

  describe('Error Handling in Complete Flow', () => {
    it('should throw error when trying to create API without required config', () => {
      expect(() => {
        new Kibaauth()
          .clientId(testConfig.clientId)
          // Missing clientSecret and callbackUrl
          .api();
      }).toThrow('Missing required configuration');
    });

    it('should validate authorization code through complete chain', async () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      const [result, error] = await api.auth().accessToken('');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Authorization code is required');
    });

    it('should validate refresh token through complete chain', async () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      const [result, error] = await api.auth().refreshToken('');
      expect(result).toBeNull();
      expect(error).toBeInstanceOf(Error);
      expect(error.message).toBe('Refresh token is required');
    });
  });

  describe('Method Chaining Variations', () => {
    it('should work with different chaining orders', () => {
      // Order 1
      const api1 = new Kibaauth()
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .clientId(testConfig.clientId)
        .api();

      // Order 2
      const api2 = new Kibaauth()
        .callbackUrl(testConfig.callbackUrl)
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .api();

      expect(api1.getConfig()).toEqual(api2.getConfig());
    });

    it('should allow overriding configuration values', () => {
      const api = new Kibaauth()
        .clientId('initial-client-id')
        .clientId(testConfig.clientId) // Override
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      expect(api.getConfig().clientId).toBe(testConfig.clientId);
    });
  });

  describe('Multiple API Client Instances', () => {
    it('should create independent API clients', () => {
      const api1 = new Kibaauth()
        .clientId('client-1')
        .clientSecret('secret-1')
        .callbackUrl('https://app1.com/callback')
        .api();

      const api2 = new Kibaauth()
        .clientId('client-2')
        .clientSecret('secret-2')
        .callbackUrl('https://app2.com/callback')
        .api();

      expect(api1.getConfig().clientId).toBe('client-1');
      expect(api2.getConfig().clientId).toBe('client-2');
      expect(api1).not.toBe(api2);
    });

    it('should create independent auth instances', () => {
      const api = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api();

      const auth1 = api.auth();
      const auth2 = api.auth();

      expect(auth1).not.toBe(auth2);
      expect(auth1).toBeInstanceOf(Auth);
      expect(auth2).toBeInstanceOf(Auth);
    });
  });

  describe('Real-world Usage Patterns', () => {
    it('should support storing and reusing API client', () => {
      const sdk = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl);

      const api = sdk.api();

      // Reuse the same API client instance
      const auth1 = api.auth();
      const auth2 = api.auth();

      expect(auth1).toBeInstanceOf(Auth);
      expect(auth2).toBeInstanceOf(Auth);
    });

    it('should support one-liner usage', () => {
      const authUrl = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .api()
        .auth()
        .authorizationUrl({ scopes: ['read'] });

      expect(authUrl).toContain(
        'client_id=' + encodeURIComponent(testConfig.clientId),
      );
      expect(authUrl).toContain('scope=read');
    });

    it('should handle complex authorization URLs', () => {
      const authUrl = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(testConfig.callbackUrl)
        .baseUrl('https://custom-auth.example.com')
        .api()
        .auth()
        .authorizationUrl({
          scopes: ['user:read', 'user:write', 'admin'],
          state: 'secure-random-state-123',
          additionalParams: {
            prompt: 'consent',
            access_type: 'offline',
            custom_param: 'custom_value',
          },
        });

      const url = new URL(authUrl);

      expect(url.origin).toBe('https://custom-auth.example.com');
      expect(url.searchParams.get('scope')).toBe('user:read user:write admin');
      expect(url.searchParams.get('state')).toBe('secure-random-state-123');
      expect(url.searchParams.get('prompt')).toBe('consent');
      expect(url.searchParams.get('access_type')).toBe('offline');
      expect(url.searchParams.get('custom_param')).toBe('custom_value');
    });
  });

  describe('Edge Cases and Robustness', () => {
    it('should handle Unicode characters in configuration', () => {
      const api = new Kibaauth()
        .clientId('клиент-ид')
        .clientSecret('секрет')
        .callbackUrl('https://приложение.com/callback')
        .api();

      const config = api.getConfig();
      expect(config.clientId).toBe('клиент-ид');
      expect(config.clientSecret).toBe('секрет');
      expect(config.callbackUrl).toBe('https://приложение.com/callback');
    });

    it('should handle very long configuration values', () => {
      const longClientId = 'a'.repeat(1000);
      const longSecret = 'b'.repeat(1000);
      const longUrl = 'https://example.com/' + 'c'.repeat(500);

      const api = new Kibaauth()
        .clientId(longClientId)
        .clientSecret(longSecret)
        .callbackUrl(longUrl)
        .api();

      const config = api.getConfig();
      expect(config.clientId).toBe(longClientId);
      expect(config.clientSecret).toBe(longSecret);
      expect(config.callbackUrl).toBe(longUrl);
    });

    it('should handle special characters in URLs', () => {
      const specialUrl =
        'https://example.com/callback?existing=param&other=value';

      const authUrl = new Kibaauth()
        .clientId(testConfig.clientId)
        .clientSecret(testConfig.clientSecret)
        .callbackUrl(specialUrl)
        .api()
        .auth()
        .authorizationUrl();

      const url = new URL(authUrl);
      expect(url.searchParams.get('redirect_uri')).toBe(specialUrl);
    });
  });
});
