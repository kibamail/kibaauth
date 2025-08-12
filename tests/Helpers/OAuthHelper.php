<?php

use App\Models\User;
use Laravel\Passport\ClientRepository;

/**
 * Create OAuth2 authorization headers using password grant flow
 *
 * @param User $user The user to authenticate
 * @param string $password The user's password (defaults to 'password')
 * @return array Authorization headers for HTTP requests
 */
function createOAuthHeaders(User $user, string $password = 'password'): array
{
    // Create a password grant client
    $clientRepository = new ClientRepository();
    $client = $clientRepository->createPasswordGrantClient(
        'Test Password Grant Client',
        $user->getProviderName()
    );

    // Make OAuth2 token request
    $response = test()->postJson('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => $client->id,
        'client_secret' => $client->secret,
        'username' => $user->email,
        'password' => $password,
        'scope' => '*',
    ]);

    // Assert the OAuth request was successful
    $response->assertStatus(200);

    // Extract the access token
    $tokenData = $response->json();
    $accessToken = $tokenData['access_token'];

    return [
        'Authorization' => 'Bearer ' . $accessToken,
    ];
}

/**
 * Create OAuth2 authorization headers for a specific client
 * This creates a new password grant client for the given user
 *
 * @param User $user The user to authenticate
 * @param string $password The user's password (defaults to 'password')
 * @return array ['headers' => auth headers, 'client' => client instance]
 */
function createOAuthHeadersForClient(User $user, string $password = 'password'): array
{
    // Create a password grant client
    $clientRepository = new ClientRepository();
    $client = $clientRepository->createPasswordGrantClient(
        'Test Password Grant Client',
        $user->getProviderName()
    );

    // Make OAuth2 token request with the created client
    $response = test()->postJson('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => $client->id,
        'client_secret' => $client->secret,
        'username' => $user->email,
        'password' => $password,
        'scope' => '*',
    ]);

    // Assert the OAuth request was successful
    $response->assertStatus(200);

    // Extract the access token
    $tokenData = $response->json();
    $accessToken = $tokenData['access_token'];

    return [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
        'client' => $client,
    ];
}
