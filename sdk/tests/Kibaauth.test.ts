import { describe, it, expect, beforeEach } from 'vitest'
import { Kibaauth } from '../src/Kibaauth'
import { ApiClient } from '../src/ApiClient'

describe('Kibaauth', () => {
  let kibaauth: Kibaauth

  beforeEach(() => {
    kibaauth = new Kibaauth()
  })

  describe('constructor', () => {
    it('should create a new instance', () => {
      expect(kibaauth).toBeInstanceOf(Kibaauth)
    })
  })

  describe('fluent API methods', () => {
    it('should set clientId and return instance for chaining', () => {
      const result = kibaauth.clientId('test-client-id')
      expect(result).toBe(kibaauth)
    })

    it('should set clientSecret and return instance for chaining', () => {
      const result = kibaauth.clientSecret('test-client-secret')
      expect(result).toBe(kibaauth)
    })

    it('should set callbackUrl and return instance for chaining', () => {
      const result = kibaauth.callbackUrl('https://example.com/callback')
      expect(result).toBe(kibaauth)
    })

    it('should set baseUrl and return instance for chaining', () => {
      const result = kibaauth.baseUrl('https://api.example.com')
      expect(result).toBe(kibaauth)
    })
  })

  describe('method chaining', () => {
    it('should allow chaining all configuration methods', () => {
      const result = kibaauth
        .clientId('test-client-id')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')
        .baseUrl('https://api.example.com')

      expect(result).toBe(kibaauth)
    })
  })

  describe('api() method', () => {
    it('should throw error when clientId is missing', () => {
      kibaauth
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')

      expect(() => kibaauth.api()).toThrow('Missing required configuration: clientId')
    })

    it('should throw error when clientSecret is missing', () => {
      kibaauth
        .clientId('test-client-id')
        .callbackUrl('https://example.com/callback')

      expect(() => kibaauth.api()).toThrow('Missing required configuration: clientSecret')
    })

    it('should throw error when callbackUrl is missing', () => {
      kibaauth
        .clientId('test-client-id')
        .clientSecret('test-client-secret')

      expect(() => kibaauth.api()).toThrow('Missing required configuration: callbackUrl')
    })

    it('should throw error when multiple required fields are missing', () => {
      expect(() => kibaauth.api()).toThrow('Missing required configuration: clientId, clientSecret, callbackUrl')
    })

    it('should return ApiClient instance when all required config is provided', () => {
      const api = kibaauth
        .clientId('test-client-id')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')
        .api()

      expect(api).toBeInstanceOf(ApiClient)
    })

    it('should return ApiClient instance with custom baseUrl', () => {
      const api = kibaauth
        .clientId('test-client-id')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')
        .baseUrl('https://custom-api.example.com')
        .api()

      expect(api).toBeInstanceOf(ApiClient)
      expect(api.getBaseUrl()).toBe('https://custom-api.example.com')
    })
  })

  describe('configuration validation', () => {
    it('should validate empty string values as missing', () => {
      kibaauth
        .clientId('')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')

      expect(() => kibaauth.api()).toThrow('Missing required configuration: clientId')
    })

    it('should accept whitespace-only strings as valid (if that\'s the intended behavior)', () => {
      kibaauth
        .clientId('   ')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')

      // This test assumes whitespace-only strings are considered valid
      // Adjust based on actual validation requirements
      expect(() => kibaauth.api()).not.toThrow()
    })
  })

  describe('integration with ApiClient', () => {
    it('should pass configuration to ApiClient correctly', () => {
      const api = kibaauth
        .clientId('test-client-id')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')
        .baseUrl('https://custom-api.example.com')
        .api()

      const config = api.getConfig()
      expect(config.clientId).toBe('test-client-id')
      expect(config.clientSecret).toBe('test-client-secret')
      expect(config.callbackUrl).toBe('https://example.com/callback')
      expect(config.baseUrl).toBe('https://custom-api.example.com')
    })

    it('should use default baseUrl when not specified', () => {
      const api = kibaauth
        .clientId('test-client-id')
        .clientSecret('test-client-secret')
        .callbackUrl('https://example.com/callback')
        .api()

      expect(api.getBaseUrl()).toBe('https://api.kibaauth.com')
    })
  })
})
