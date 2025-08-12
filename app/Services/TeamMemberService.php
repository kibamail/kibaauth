<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TeamMemberService
{
    /**
     * @param Team $team
     * @param array $data
     * @return TeamMember
     */
    public function createTeamMember(Team $team, array $data): TeamMember
    {
        $userData = $this->resolveUserData($data);

        $this->validateUniqueTeamMembership($team, $userData);

        $teamMember = new TeamMember([
            'team_id' => $team->id,
            'user_id' => $userData['user_id'],
            'email' => $userData['email'],
            'status' => $data['status'] ?? 'pending',
        ]);

        $teamMember->save();
        return $teamMember;
    }

    public function deleteTeamMember(TeamMember $teamMember): void
    {
        $teamMember->delete();
    }

    /**
     * @param array $data
     * @return array
     */
    private function resolveUserData(array $data): array
    {
        if (!empty($data['user_id'])) {
            return $this->handleUserIdInput($data['user_id']);
        }

        return $this->handleEmailInput($data['email']);
    }

    /**
     * @param string $userId
     * @return array<string,mixed>
     */
    private function handleUserIdInput(string $userId): array
    {
        $user = User::query()->find($userId);

        if (!$user) {
            abort(404, 'User not found');
        }

        return [
            'user_id' => $userId,
            'email' => null,
        ];
    }

    /**
     * @param string $email
     * @return array<string,mixed>
     */
    private function handleEmailInput(string $email): array
    {
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            return [
                'user_id' => $existingUser->id,
                'email' => null,
                'existing_user' => true,
            ];
        }

        return [
            'user_id' => null,
            'email' => $email,
            'existing_user' => false,
        ];
    }

    /**
     * @param Team $team
     * @param array $userData
     * @return void
     */
    private function validateUniqueTeamMembership(Team $team, array $userData): void
    {
        if ($userData['user_id']) {
            $this->validateUserNotInTeam($team, $userData['user_id'], $userData['existing_user'] ?? false);
        } else {
            $this->validateEmailNotInvited($team, $userData['email']);
        }
    }

    private function validateUserNotInTeam(Team $team, string $userId, bool $fromEmail = false): void
    {
        $existingMember = TeamMember::query()->where('team_id', $team->id)
            ->where('user_id', $userId)
            ->first();

        if ($existingMember) {
            $field = $fromEmail ? 'email' : 'user_id';
            $message = $fromEmail
                ? 'A user with this email is already a member of this team.'
                : 'This user is already a member of this team.';

            throw ValidationException::withMessages([
                $field => [$message]
            ]);
        }
    }

    private function validateEmailNotInvited(Team $team, string $email): void
    {
        $existingMember = TeamMember::query()->where('team_id', $team->id)
            ->where('email', $email)
            ->first();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => ['This email has already been invited to this team.']
            ]);
        }
    }
}
