<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Client as PassportClient;

class Client extends PassportClient
{

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return true;
    }


    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }


    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::created(function ($client) {
            // Auto-create default permissions for new clients
            $defaultPermissions = [
                [
                    'name' => 'Create Teams',
                    'slug' => 'teams:create',
                    'description' => 'Permission to create teams within workspaces'
                ],
                [
                    'name' => 'Update Teams',
                    'slug' => 'teams:update',
                    'description' => 'Permission to update existing teams'
                ],
                [
                    'name' => 'Delete Teams',
                    'slug' => 'teams:delete',
                    'description' => 'Permission to delete teams'
                ],
                [
                    'name' => 'View Teams',
                    'slug' => 'teams:view',
                    'description' => 'Permission to view and list teams'
                ],
                [
                    'name' => 'Create Team Members',
                    'slug' => 'teamMembers:create',
                    'description' => 'Permission to add members to teams'
                ],
                [
                    'name' => 'Update Team Members',
                    'slug' => 'teamMembers:update',
                    'description' => 'Permission to update team member information'
                ],
                [
                    'name' => 'Delete Team Members',
                    'slug' => 'teamMembers:delete',
                    'description' => 'Permission to remove members from teams'
                ],
                [
                    'name' => 'View Team Members',
                    'slug' => 'teamMembers:view',
                    'description' => 'Permission to view and list team members'
                ]
            ];

            foreach ($defaultPermissions as $permissionData) {
                Permission::create([
                    'name' => $permissionData['name'],
                    'slug' => $permissionData['slug'],
                    'description' => $permissionData['description'],
                    'client_id' => $client->id,
                ]);
            }
        });
    }
}
