<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function createTeam(Workspace $workspace, array $data): Team
    {
        $slug = $data['slug'] ?? $data['name'];
        $uniqueSlug = Team::generateUniqueSlug($slug, $workspace->id);

        $team = $workspace->teams()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $uniqueSlug,
        ]);

        if (!empty($data['permission_ids'])) {
            $team->permissions()->attach($data['permission_ids']);
        }

        return $team;
    }

    public function updateTeam(Team $team, Workspace $workspace, array $data): Team
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['slug'])) {
            if ($data['slug'] !== $team->slug) {
                $updateData['slug'] = Team::generateUniqueSlug($data['slug'], $workspace->id);
            }
        }

        if (!empty($updateData)) {
            $team->update($updateData);
        }

        if (isset($data['permission_ids'])) {
            $team->permissions()->sync($data['permission_ids']);
        }

        return $team;
    }

    public function deleteTeam(Team $team): void
    {
        $team->delete();
    }

    public function syncTeamPermissions(Team $team, array $permissionIds, string $clientId): Team
    {
        $this->validatePermissionsBelongToClient($permissionIds, $clientId);

        $team->permissions()->sync($permissionIds);

        return $team;
    }

    private function validatePermissionsBelongToClient(array $permissionIds, string $clientId): void
    {
        $invalidPermissions = Permission::whereIn('id', $permissionIds)
            ->where('client_id', '!=', $clientId)
            ->exists();

        if ($invalidPermissions) {
            throw ValidationException::withMessages([
                'permission_ids' => ['One or more permissions do not belong to this client.']
            ]);
        }
    }
}
