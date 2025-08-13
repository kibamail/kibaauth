<?php

namespace App\Services\User;

use App\Models\Permission;

class UserPermissionService
{
    /**
     * Get all permissions available for the current client.
     */
    public function getClientPermissions(string $clientId): array
    {
        return Permission::where('client_id', $clientId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'created_at', 'updated_at'])
            ->toArray();
    }
}
