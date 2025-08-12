<?php

namespace App\Authorization;

use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class TeamAuthorization
{
    public function validateWorkspaceContext(Workspace $workspace, string $clientId): void
    {
        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }
    }

    public function validateTeamContext(Team $team, Workspace $workspace): void
    {
        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }
    }

    public function getClientId(Request $request): string
    {
        $token = $request->user()->token();
        $clientId = $token->client_id ?? $token->client->id ?? null;

        if (!$clientId) {
            abort(400, 'Client context not available');
        }

        return $clientId;
    }
}
