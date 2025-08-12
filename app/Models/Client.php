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
}
