<?php

namespace App\Http\Controllers;

use App\Authorization\TeamMemberAuthorization;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Workspace;
use App\Services\TeamMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class TeamMemberController extends Controller
{
    public function __construct(
        private TeamMemberAuthorization $authorization,
        private TeamMemberService $service
    ) {}

    public function store(StoreTeamMemberRequest $request, Workspace $workspace, Team $team): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $user = $request->user();

        $this->authorization->validateContextAndAuthorization($user, $workspace, $team, $clientId);

        $teamMember = $this->service->createTeamMember($team, $request->validated());

        if ($teamMember->user_id) {
            $teamMember->load('user');
        }

        return response()->json([
            'data' => $teamMember,
            'message' => 'Team member added successfully',
        ], 201);
    }

    public function destroy(Request $request, Workspace $workspace, Team $team, TeamMember $teamMember): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $user = $request->user();

        $this->authorization->validateDeleteAuthorization($user, $workspace, $team, $teamMember, $clientId);

        $this->service->deleteTeamMember($teamMember);

        return response()->json([
            'message' => 'Team member removed successfully',
        ], 200);
    }


}
