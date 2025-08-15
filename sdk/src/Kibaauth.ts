import { ApiClient } from './ApiClient';

/**
 * Configuration interface for Kibaauth client
 */
export interface KibaauthConfig {
  /** OAuth2 client ID */
  clientId?: string;
  /** OAuth2 client secret */
  clientSecret?: string;
  /** OAuth2 callback URL for redirect after authentication */
  callbackUrl?: string;
  /** Access token for authenticated requests */
  accessToken?: string;
  /** Base URL for the API (optional, defaults to production URL) */
  baseUrl?: string;
}

/**
 * Main Kibaauth SDK class providing a fluent API for OAuth2 authentication
 *
 * @example
 * ```typescript
 * const api = new Kibaauth()
 *   .clientId('your-client-id')
 *   .clientSecret('your-client-secret')
 *   .callbackUrl('https://your-app.com/callback')
 *   .api()
 *
 * const response = await api.auth().accessToken('authorization-code')
 * ```
 */
export class Kibaauth {
  private config: KibaauthConfig = {};

  /**
   * Set the OAuth2 client ID
   *
   * @param clientId - The OAuth2 client ID provided by Kibaauth
   * @returns The Kibaauth instance for method chaining
   */
  clientId(clientId: string): this {
    this.config.clientId = clientId;
    return this;
  }

  /**
   * Set the OAuth2 client secret
   *
   * @param clientSecret - The OAuth2 client secret provided by Kibaauth
   * @returns The Kibaauth instance for method chaining
   */
  clientSecret(clientSecret: string): this {
    this.config.clientSecret = clientSecret;
    return this;
  }

  /**
   * Set the OAuth2 callback URL
   *
   * @param callbackUrl - The callback URL where users will be redirected after authentication
   * @returns The Kibaauth instance for method chaining
   */
  callbackUrl(callbackUrl: string): this {
    this.config.callbackUrl = callbackUrl;
    return this;
  }

  /**
   * Set the access token for authenticated requests
   *
   * @param accessToken - The access token for API authentication
   * @returns The Kibaauth instance for method chaining
   */
  accessToken(accessToken: string): this {
    this.config.accessToken = accessToken;
    return this;
  }

  /**
   * Set the base URL for the API
   *
   * @param baseUrl - The base URL for the Kibaauth API
   * @returns The Kibaauth instance for method chaining
   */
  baseUrl(baseUrl: string): this {
    this.config.baseUrl = baseUrl;
    return this;
  }

  /**
   * Create and return an API client instance with the configured settings
   *
   * @returns An ApiClient instance configured with the current settings
   * @throws Error if required configuration is missing
   */
  api(): ApiClient {
    this.validateConfig();
    return new ApiClient(this.config);
  }

  /**
   * Validate that all required configuration is present
   *
   * @private
   * @throws Error if required configuration is missing
   */
  private validateConfig(): void {
    // If access token is provided, only base URL is required
    if (this.config.accessToken) {
      return;
    }

    // If no access token, require OAuth2 credentials
    const required = ['clientId', 'clientSecret', 'callbackUrl'] as const;
    const missing = required.filter((key) => !this.config[key]);

    if (missing.length > 0) {
      throw new Error(`Missing required configuration: ${missing.join(', ')}`);
    }
  }
}
