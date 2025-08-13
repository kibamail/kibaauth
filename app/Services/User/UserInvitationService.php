<?php

namespace App\Services\User;

use App\Models\User;

class UserInvitationService
{
    /**
     * Get pending invitations for the user.
     */
    public function getPendingInvitations(User $user, string $clientId): array
    {
        return $user->teamMembers()
            ->where('status', 'pending')
            ->with([
                'team.workspace' => function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                },
                'team'
            ])
            ->get()
            ->filter(function ($teamMember) {
                return $teamMember->team && $teamMember->team->workspace;
            })
            ->map(function ($teamMember) {
                return $this->formatInvitation($teamMember);
            })
            ->values()
            ->toArray();
    }

    /**
     * Format a team member invitation for API response.
     */
    protected function formatInvitation($teamMember): array
    {
        $workspace = $teamMember->team->workspace;

        return [
            'invitation_id' => $teamMember->id,
            'team_id' => $teamMember->team->id,
            'team_name' => $teamMember->team->name,
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'created_at' => $workspace->created_at,
                'updated_at' => $workspace->updated_at,
            ],
            'invited_at' => $teamMember->created_at,
            'status' => $teamMember->status,
        ];
    }
}
