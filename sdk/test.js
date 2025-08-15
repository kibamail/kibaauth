#!/usr/bin/env node

/**
 * Test script for KibaAuth SDK User API endpoints
 *
 * This script can be run directly with: node user-api-test.js
 */

import { readFileSync } from 'fs';
import { Kibaauth } from './dist/index.js';

// Test credentials for Laravel Passport client
const CLIENT_ID = '0198afbc-76f8-720b-a9d7-b52260b69b06';
const CLIENT_SECRET = 'zTt3SUMeKmrF27qgaAZ4zqIjuxsb5pXVmXdr6Ct8';
const REDIRECT_URI = 'http://localhost:3333/auth/callback';
const BASE_URL = 'http://localhost:8000';

// Read access token from file
let ACCESS_TOKEN;
try {
  ACCESS_TOKEN = readFileSync('./access_token.txt', 'utf8').trim();
} catch (error) {
  console.error('❌ Could not read access token from access_token.txt');
  console.error('Make sure the file exists and contains a valid access token');
  process.exit(1);
}

console.log('👤 KibaAuth User API Test');
console.log('========================');
console.log();

async function testUserAPI() {
  const api = new Kibaauth().accessToken(ACCESS_TOKEN).baseUrl(BASE_URL).api();
  const authorization = new Kibaauth()
    .clientId(CLIENT_ID)
    .clientSecret(CLIENT_SECRET)
    .callbackUrl(REDIRECT_URI)
    .baseUrl(BASE_URL)
    .api();

  console.log(
    'authorization.auth().authorizationUrl()',
    authorization.auth().authorizationUrl(),
  );

  const [profile] = await api.user().profile();

  console.dir(profile, { depth: null });
}

testUserAPI();
