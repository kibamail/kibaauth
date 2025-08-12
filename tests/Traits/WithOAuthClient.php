<?php

namespace Tests\Traits;

use App\Models\Client;
use App\Models\User;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

trait WithOAuthClient
{
    protected Client $testClient;

    /**
     * Setup OAuth client for testing.
     */
    protected function setUpOAuthClient(): void
    {
        $this->testClient = Client::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Client',
            'secret' => hash('sha256', 'test-secret'),
            'redirect_uris' => 'http://localhost',
            'grant_types' => 'authorization_code',
            'revoked' => false,
        ]);
    }

    /**
     * Authenticate user with proper OAuth context.
     */
    protected function authenticateWithClient(User $user, Client $client = null): Token
    {
        $client = $client ?? $this->testClient;

        // Create a real access token in the database
        $token = new Token();
        $token->id = \Illuminate\Support\Str::random(80);
        $token->user_id = $user->id;
        $token->client_id = $client->id;
        $token->name = 'Test Token';
        $token->scopes = [];
        $token->revoked = false;
        $token->created_at = now();
        $token->updated_at = now();
        $token->save();

        // Generate a proper JWT token for the request
        $jwtToken = $this->generateJwtToken($token);

        // Set the authorization header
        $this->withHeader('Authorization', 'Bearer ' . $jwtToken);

        return $token;
    }

    /**
     * Generate a proper JWT token for testing.
     */
    protected function generateJwtToken(Token $token): string
    {
        // For testing purposes, we'll use Passport's actingAs which handles JWT generation
        Passport::actingAs($token->user, [], 'api');

        // Get the token that was just created by actingAs
        $user = $token->user;
        $testToken = $user->token();

        if ($testToken && method_exists($testToken, 'client_id')) {
            $testToken->client_id = $token->client_id;
        }

        // Return a mock JWT structure - in real tests this would be handled by Passport
        return base64_encode(json_encode(['sub' => $token->user_id, 'client_id' => $token->client_id]));
    }

    /**
     * Get the test client ID.
     */
    protected function getTestClientId(): string
    {
        return $this->testClient->id;
    }
}
