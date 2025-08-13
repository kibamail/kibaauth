<?php

namespace App\Services\User;

use App\Models\User;

class UserDataService
{
    public function __construct(
        protected UserWorkspaceService $workspaceService,
        protected UserInvitationService $invitationService,
        protected UserPermissionService $permissionService
    ) {}

    /**
     * Assemble complete user data for API response.
     */
    public function assembleUserData(User $user, string $clientId): array
    {
        $workspaces = $this->workspaceService->getUserWorkspaces($user, $clientId);

        foreach ($workspaces as $workspace) {
            $this->workspaceService->loadTeamsForWorkspace($workspace, $user);
        }

        $pendingInvitations = $this->invitationService->getPendingInvitations($user, $clientId);
        $clientPermissions = $this->permissionService->getClientPermissions($clientId);

        return array_merge($this->getUserBasicData($user), [
            'workspaces' => $workspaces,
            'pending_invitations' => $pendingInvitations,
            'client_permissions' => $clientPermissions
        ]);
    }

    /**
     * Get basic user data without sensitive information.
     */
    protected function getUserBasicData(User $user): array
    {
        return $user->only(['id', 'email', 'email_verified_at', 'created_at', 'updated_at']);
    }
}
