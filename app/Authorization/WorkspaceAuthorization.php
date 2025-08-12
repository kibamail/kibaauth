<?php

namespace App\Authorization;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceAuthorization
{
    public function validateWorkspaceAccess(User $user, Workspace $workspace, string $clientId): void
    {
        if (!$this->workspaceBelongsToClient($workspace, $clientId)) {
            abort(404, 'Workspace not found');
        }

        if (!$this->canAccessWorkspace($user, $workspace)) {
            abort(403, 'You are not authorized to access this workspace');
        }
    }

    public function validateWorkspaceOwnership(User $user, Workspace $workspace, string $clientId): void
    {
        if (!$this->workspaceBelongsToClient($workspace, $clientId)) {
            abort(404, 'Workspace not found');
        }

        if (!$this->isWorkspaceOwner($user, $workspace)) {
            abort(403, 'You are not authorized to perform this action');
        }
    }

    public function validateClientContext(string $clientId): void
    {
        if (!$clientId) {
            abort(400, 'Client context not available');
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

    private function isWorkspaceOwner(User $user, Workspace $workspace): bool
    {
        return $workspace->user_id === $user->id;
    }

    private function canAccessWorkspace(User $user, Workspace $workspace): bool
    {
        return $workspace->user_id === $user->id;
    }

    private function workspaceBelongsToClient(Workspace $workspace, string $clientId): bool
    {
        return $workspace->client_id === $clientId;
    }
}
