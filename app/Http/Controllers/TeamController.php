<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Workspace;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Services\TeamService;
use App\Authorization\TeamAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $authorization = new TeamAuthorization();
        $clientId = $authorization->getClientId($request);
        $authorization->validateWorkspaceContext($workspace, $clientId);

        $teams = $workspace->teams()
            ->with('permissions')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $teams,
        ]);
    }

    public function store(StoreTeamRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $authorization = new TeamAuthorization();
        $clientId = $authorization->getClientId($request);
        $authorization->validateWorkspaceContext($workspace, $clientId);

        $service = new TeamService();
        $team = $service->createTeam($workspace, $request->validated());

        $team->load('permissions');

        return response()->json([
            'data' => $team,
            'message' => 'Team created successfully',
        ], 201);
    }

    public function show(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('view', $workspace);

        $authorization = new TeamAuthorization();
        $clientId = $authorization->getClientId($request);
        $authorization->validateWorkspaceContext($workspace, $clientId);
        $authorization->validateTeamContext($team, $workspace);

        $team->load('permissions');

        return response()->json([
            'data' => $team,
        ]);
    }

    public function update(UpdateTeamRequest $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('update', $workspace);

        $authorization = new TeamAuthorization();
        $clientId = $authorization->getClientId($request);
        $authorization->validateWorkspaceContext($workspace, $clientId);
        $authorization->validateTeamContext($team, $workspace);

        $service = new TeamService();
        $team = $service->updateTeam($team, $workspace, $request->validated());

        $team->load('permissions');

        return response()->json([
            'data' => $team->fresh(['permissions']),
            'message' => 'Team updated successfully',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('update', $workspace);

        $authorization = new TeamAuthorization();
        $clientId = $authorization->getClientId($request);
        $authorization->validateWorkspaceContext($workspace, $clientId);
        $authorization->validateTeamContext($team, $workspace);

        $service = new TeamService();
        $service->deleteTeam($team);

        return response()->json([
            'message' => 'Team deleted successfully',
        ]);
    }

    public function syncPermissions(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('update', $workspace);

        $authorization = new TeamAuthorization();
        $clientId = $authorization->getClientId($request);
        $authorization->validateWorkspaceContext($workspace, $clientId);
        $authorization->validateTeamContext($team, $workspace);

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'uuid|exists:permissions,id',
        ]);

        $service = new TeamService();
        $team = $service->syncTeamPermissions($team, $validated['permission_ids'], $clientId);

        $team->load('permissions');

        return response()->json([
            'data' => $team,
            'message' => 'Team permissions synced successfully',
        ]);
    }
}
