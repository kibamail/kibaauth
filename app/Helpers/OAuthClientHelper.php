<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class OAuthClientHelper
{
    /**
     * Get the client ID from the request token.
     */
    public static function getClientId(Request $request): string
    {
        $token = $request->user()->token();
        $clientId = $token->client_id ?? $token->client->id ?? null;

        if (!$clientId) {
            abort(400, 'Client context not available');
        }

        return $clientId;
    }
}
