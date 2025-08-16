import { $trycatch } from '@tszen/trycatch';
import axios, { type AxiosInstance, type AxiosRequestConfig } from 'axios';
import { Auth } from './Auth';
import { Team } from './Team';
import { User } from './User';
import { Workspace } from './Workspace';

/**
 * Configuration interface for the API client
 */
export interface ApiClientConfig {
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
 * HTTP client options for requests
 */
export interface RequestOptions {
  /** Request headers */
  headers?: Record<string, string>;
  /** Request timeout in milliseconds */
  timeout?: number;
  /** Additional axios options */
  [key: string]: any;
}

/**
 * API client for making authenticated requests to the Kibaauth API
 *
 * @example
 * ```typescript
 * const api = new ApiClient({
 *   clientId: 'your-client-id',
 *   clientSecret: 'your-client-secret',
 *   callbackUrl: 'https://your-app.com/callback'
 * })
 *
 * const authResponse = await api.auth().accessToken('authorization-code')
 * ```
 */
export class ApiClient {
  private config: ApiClientConfig;
  private defaultHeaders: Record<string, string>;
  private httpClient: AxiosInstance;

  /**
   * Create a new API client instance
   *
   * @param config - Configuration for the API client
   */
  constructor(config: ApiClientConfig) {
    this.config = {
      ...config,
      baseUrl: config.baseUrl || 'https://api.kibaauth.com',
    };

    this.defaultHeaders = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'User-Agent': '@kibaauth/sdk/1.0.0',
    };

    // Add authorization header if access token is provided
    if (config.accessToken) {
      this.defaultHeaders.Authorization = `Bearer ${config.accessToken}`;
    }

    // Initialize axios instance
    this.httpClient = axios.create({
      baseURL: this.config.baseUrl,
      headers: this.defaultHeaders,
      timeout: 30000, // 30 seconds default timeout
    });
  }

  /**
   * Get the authentication module for OAuth2 operations
   *
   * @returns Auth instance for handling authentication flows
   */
  auth(): Auth {
    return new Auth(this);
  }

  /**
   * Get the user module for user-related operations
   *
   * @returns User instance for handling user operations
   */
  user(): User {
    return new User(this);
  }

  /**
   * Get the workspaces module for workspace-related operations
   *
   * @returns Workspace instance for handling workspace operations
   */
  workspaces(): Workspace {
    return new Workspace(this);
  }

  /**
   * Get the teams module for team-related operations
   *
   * @returns Team instance for handling team operations
   */
  teams(): Team {
    return new Team(this);
  }

  /**
   * Get the base URL for API requests
   *
   * @returns The configured base URL
   */
  getBaseUrl(): string {
    return this.config.baseUrl || 'https://api.kibaauth.com';
  }

  /**
   * Get the client configuration
   *
   * @returns The API client configuration
   */
  getConfig(): ApiClientConfig {
    return { ...this.config };
  }

  /**
   * Get the default headers for requests
   *
   * @returns Default headers object
   */
  getDefaultHeaders(): Record<string, string> {
    return { ...this.defaultHeaders };
  }

  /**
   * Make an HTTP GET request
   *
   * @param endpoint - The API endpoint (relative to base URL)
   * @param options - Additional request options
   * @returns Promise resolving to a tuple [result, error]
   */
  async get<T = any>(
    endpoint: string,
    options?: RequestOptions,
  ): Promise<[T | null, any]> {
    const [result, error] = await $trycatch(async () => {
      const config: AxiosRequestConfig = {
        ...options,
        headers: {
          ...this.defaultHeaders,
          ...options?.headers,
        },
      };

      const response = await this.httpClient.get(endpoint, config);

      if (response?.data?.data) {
        return response?.data?.data;
      }
      return response.data;
    });

    if (error) {
      return [null, error];
    }

    return [result, null];
  }

  /**
   * Make an HTTP POST request
   *
   * @param endpoint - The API endpoint (relative to base URL)
   * @param data - Request body data
   * @param options - Additional request options
   * @returns Promise resolving to a tuple [result, error]
   */
  async post<T = any>(
    endpoint: string,
    data?: any,
    options?: RequestOptions,
  ): Promise<[T | null, any]> {
    const [result, error] = await $trycatch(async () => {
      const config: AxiosRequestConfig = {
        ...options,
        headers: {
          ...this.defaultHeaders,
          ...options?.headers,
        },
      };

      const response = await this.httpClient.post(endpoint, data, config);
      if (response?.data?.data) {
        return response?.data?.data;
      }
      return response.data;
    });

    if (error) {
      return [null, error];
    }

    return [result, null];
  }

  /**
   * Make an HTTP PUT request
   *
   * @param endpoint - The API endpoint (relative to base URL)
   * @param data - Request body data
   * @param options - Additional request options
   * @returns Promise resolving to a tuple [result, error]
   */
  async put<T = any>(
    endpoint: string,
    data?: any,
    options?: RequestOptions,
  ): Promise<[T | null, any]> {
    const [result, error] = await $trycatch(async () => {
      const config: AxiosRequestConfig = {
        ...options,
        headers: {
          ...this.defaultHeaders,
          ...options?.headers,
        },
      };

      const response = await this.httpClient.put(endpoint, data, config);
      if (response?.data?.data) {
        return response?.data?.data;
      }
      return response.data;
    });

    if (error) {
      return [null, error];
    }

    return [result, null];
  }

  /**
   * Make an HTTP PATCH request
   *
   * @param endpoint - The API endpoint (relative to base URL)
   * @param data - Request body data
   * @param options - Additional request options
   * @returns Promise resolving to a tuple [result, error]
   */
  async patch<T = any>(
    endpoint: string,
    data?: any,
    options?: RequestOptions,
  ): Promise<[T | null, any]> {
    const [result, error] = await $trycatch(async () => {
      const config: AxiosRequestConfig = {
        ...options,
        headers: {
          ...this.defaultHeaders,
          ...options?.headers,
        },
      };

      const response = await this.httpClient.patch(endpoint, data, config);
      if (response?.data?.data) {
        return response?.data?.data;
      }
      return response.data;
    });

    if (error) {
      return [null, error];
    }

    return [result, null];
  }

  /**
   * Make an HTTP DELETE request
   *
   * @param endpoint - The API endpoint (relative to base URL)
   * @param options - Additional request options
   * @returns Promise resolving to a tuple [result, error]
   */
  async delete<T = any>(
    endpoint: string,
    options?: RequestOptions,
  ): Promise<[T | null, any]> {
    const [result, error] = await $trycatch(async () => {
      const config: AxiosRequestConfig = {
        ...options,
        headers: {
          ...this.defaultHeaders,
          ...options?.headers,
        },
      };

      const response = await this.httpClient.delete(endpoint, config);
      if (response?.data?.data) {
        return response?.data?.data;
      }
      return response.data;
    });

    if (error) {
      return [null, error];
    }

    return [result, null];
  }
}
