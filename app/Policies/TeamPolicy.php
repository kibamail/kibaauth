<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->id === $team->workspace->user_id;
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->id === $team->workspace->user_id;
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->id === $team->workspace->user_id;
    }

    /**
     * Determine whether the user can restore the team.
     */
    public function restore(User $user, Team $team): bool
    {
        return $user->id === $team->workspace->user_id;
    }

    /**
     * Determine whether the user can permanently delete the team.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        return $user->id === $team->workspace->user_id;
    }
}
