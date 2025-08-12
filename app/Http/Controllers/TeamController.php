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
    private TeamAuthorization $authorization;
    private TeamService $service;

    public function __construct(
        TeamAuthorization $authorization,
        TeamService $service
    ) {
        $this->authorization = $authorization;
        $this->service = $service;
    }

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $this->authorization->validateWorkspaceContext($workspace, $clientId);

        // Check if user has teams:view permission or is workspace owner
        if (!$this->authorization->userHasPermissionInWorkspace($request->user(), $workspace, 'teams:view')) {
            abort(403, 'You do not have permission to view teams in this workspace');
        }

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
        $clientId = $this->authorization->getClientId($request);
        $this->authorization->validateWorkspaceContext($workspace, $clientId);

        // Check if user has teams:create permission or is workspace owner
        if (!$this->authorization->userHasPermissionInWorkspace($request->user(), $workspace, 'teams:create')) {
            abort(403, 'You do not have permission to create teams in this workspace');
        }

        $team = $this->service->createTeam($workspace, $request->validated());

        $team->load('permissions');

        return response()->json([
            'data' => $team,
            'message' => 'Team created successfully',
        ], 201);
    }

    public function show(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $this->authorization->validateWorkspaceContext($workspace, $clientId);
        $this->authorization->validateTeamContext($team, $workspace);

        // Check if user has teams:view permission or is workspace owner
        if (!$this->authorization->userHasPermissionInWorkspace($request->user(), $workspace, 'teams:view')) {
            abort(403, 'You do not have permission to view teams in this workspace');
        }

        $team->load('permissions');

        return response()->json([
            'data' => $team,
        ]);
    }

    public function update(UpdateTeamRequest $request, Workspace $workspace, Team $team): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $this->authorization->validateWorkspaceContext($workspace, $clientId);
        $this->authorization->validateTeamContext($team, $workspace);

        // Check if user has teams:update permission or is workspace owner
        if (!$this->authorization->userHasPermissionInWorkspace($request->user(), $workspace, 'teams:update')) {
            abort(403, 'You do not have permission to update teams in this workspace');
        }

        $team = $this->service->updateTeam($team, $workspace, $request->validated());

        $team->load('permissions');

        return response()->json([
            'data' => $team->fresh(['permissions']),
            'message' => 'Team updated successfully',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $this->authorization->validateWorkspaceContext($workspace, $clientId);
        $this->authorization->validateTeamContext($team, $workspace);

        // Check if user has teams:delete permission or is workspace owner
        if (!$this->authorization->userHasPermissionInWorkspace($request->user(), $workspace, 'teams:delete')) {
            abort(403, 'You do not have permission to delete teams in this workspace');
        }

        $this->service->deleteTeam($team);

        return response()->json([
            'message' => 'Team deleted successfully',
        ]);
    }

    public function syncPermissions(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $clientId = $this->authorization->getClientId($request);
        $this->authorization->validateWorkspaceContext($workspace, $clientId);
        $this->authorization->validateTeamContext($team, $workspace);

        // Check if user has teams:update permission or is workspace owner
        if (!$this->authorization->userHasPermissionInWorkspace($request->user(), $workspace, 'teams:update')) {
            abort(403, 'You do not have permission to update team permissions in this workspace');
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'uuid|exists:permissions,id',
        ]);

        $team = $this->service->syncTeamPermissions($team, $validated['permission_ids'], $clientId);

        $team->load('permissions');

        return response()->json([
            'data' => $team,
            'message' => 'Team permissions synced successfully',
        ]);
    }
}
