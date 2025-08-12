<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Workspace;
use App\Models\Permission;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    /**
     * Display a listing of teams for a specific workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        $teams = $workspace->teams()
            ->with('permissions')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $teams,
        ]);
    }

    /**
     * Store a newly created team in storage.
     */
    public function store(StoreTeamRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace); // Only workspace owner can create teams

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        $validated = $request->validated();

        $slug = $validated['slug'] ?? $validated['name'];
        $uniqueSlug = Team::generateUniqueSlug($slug, $workspace->id);

        $team = $workspace->teams()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'slug' => $uniqueSlug,
        ]);

        // Attach permissions if provided
        if (!empty($validated['permission_ids'])) {
            $team->permissions()->attach($validated['permission_ids']);
        }

        // Load the permissions relationship for response
        $team->load('permissions');

        return response()->json([
            'data' => $team,
            'message' => 'Team created successfully',
        ], 201);
    }

    /**
     * Display the specified team.
     */
    public function show(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('view', $workspace);

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }

        $team->load('permissions');

        return response()->json([
            'data' => $team,
        ]);
    }

    /**
     * Update the specified team in storage.
     */
    public function update(UpdateTeamRequest $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('update', $workspace); // Only workspace owner can update teams

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }

        $validated = $request->validated();

        $data = [];

        if (isset($validated['name'])) {
            $data['name'] = $validated['name'];
        }

        if (isset($validated['description'])) {
            $data['description'] = $validated['description'];
        }

        if (isset($validated['slug'])) {
            if ($validated['slug'] !== $team->slug) {
                $data['slug'] = Team::generateUniqueSlug($validated['slug'], $workspace->id);
            }
        }

        if (!empty($data)) {
            $team->update($data);
        }

        // Update permissions if provided
        if (isset($validated['permission_ids'])) {
            $team->permissions()->sync($validated['permission_ids']);
        }

        // Load the permissions relationship for response
        $team->load('permissions');

        return response()->json([
            'data' => $team->fresh(['permissions']),
            'message' => 'Team updated successfully',
        ]);
    }

    /**
     * Remove the specified team from storage.
     */
    public function destroy(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('update', $workspace); // Only workspace owner can delete teams

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }

        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully',
        ]);
    }

    /**
     * Get the client ID from the request token.
     */
    protected function getClientId(Request $request): string
    {
        $token = $request->user()->token();
        $clientId = $token->client_id ?? $token->client->id ?? null;

        if (!$clientId) {
            abort(400, 'Client context not available');
        }

        return $clientId;
    }

    /**
     * Sync permissions for a specific team.
     */
    public function syncPermissions(Request $request, Workspace $workspace, Team $team): JsonResponse
    {
        $this->authorize('update', $workspace); // Only workspace owner can sync permissions

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        if ($team->workspace_id !== $workspace->id) {
            abort(404, 'Team not found in this workspace');
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        // Validate that all permissions belong to the same client
        $invalidPermissions = Permission::whereIn('id', $validated['permission_ids'])
            ->where('client_id', '!=', $clientId)
            ->exists();

        if ($invalidPermissions) {
            throw ValidationException::withMessages([
                'permission_ids' => ['One or more permissions do not belong to this client.']
            ]);
        }

        // Sync the permissions
        $team->permissions()->sync($validated['permission_ids']);

        // Load the permissions relationship for response
        $team->load('permissions');

        return response()->json([
            'data' => $team,
            'message' => 'Team permissions synced successfully',
        ]);
    }
}
