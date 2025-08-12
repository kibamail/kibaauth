<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\User;

class WorkspaceService
{
    public function createWorkspace(User $user, array $data, string $clientId): Workspace
    {
        $slug = $data['slug'] ?? $data['name'];
        $uniqueSlug = Workspace::generateUniqueSlug($slug, $clientId);

        return $user->workspaces()->create([
            'name' => $data['name'],
            'slug' => $uniqueSlug,
            'client_id' => $clientId,
        ]);
    }

    public function updateWorkspace(Workspace $workspace, array $data, string $clientId): Workspace
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['slug'])) {
            if ($data['slug'] !== $workspace->slug) {
                $updateData['slug'] = Workspace::generateUniqueSlug($data['slug'], $clientId);
            }
        }

        if (!empty($updateData)) {
            $workspace->update($updateData);
        }

        return $workspace;
    }

    public function deleteWorkspace(Workspace $workspace): void
    {
        $workspace->delete();
    }

    public function getUserWorkspaces(User $user, string $clientId)
    {
        return $user->workspaces()
            ->where('client_id', $clientId)
            ->with(['teams.permissions'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
