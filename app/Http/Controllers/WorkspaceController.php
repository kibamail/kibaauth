<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Requests\StoreWorkspaceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Workspace::class);

        $clientId = $this->getClientId($request);

        $workspaces = $request->user()->workspaces()
            ->where('client_id', $clientId)
            ->with(['teams.permissions'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $workspaces,
        ]);
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $this->authorize('create', Workspace::class);

        $clientId = $this->getClientId($request);
        $slug = $request->slug ?? $request->name;
        $uniqueSlug = Workspace::generateUniqueSlug($slug, $clientId);

        $workspace = $request->user()->workspaces()->create([
            'name' => $request->name,
            'slug' => $uniqueSlug,
            'client_id' => $clientId,
        ]);

        return response()->json([
            'data' => $workspace,
            'message' => 'Workspace created successfully',
        ], 201);
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        $workspace->load(['teams.permissions']);

        return response()->json([
            'data' => $workspace,
        ]);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|alpha_dash',
        ]);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }

        if ($request->has('slug')) {
            if ($request->slug !== $workspace->slug) {
                $data['slug'] = Workspace::generateUniqueSlug($request->slug, $clientId);
            }
        }

        if (!empty($data)) {
            $workspace->update($data);
        }

        return response()->json([
            'data' => $workspace->fresh(['teams.permissions']),
            'message' => 'Workspace updated successfully',
        ]);
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);

        $clientId = $this->getClientId($request);

        if ($workspace->client_id !== $clientId) {
            abort(404, 'Workspace not found');
        }

        $workspace->delete();

        return response()->json([
            'message' => 'Workspace deleted successfully',
        ]);
    }

    public function getClientId(Request $request): string
    {
        $token = $request->user()->token();
        $clientId = $token->client_id ?? $token->client->id ?? null;

        if (!$clientId) {
            abort(400, 'Client context not available');
        }

        return $clientId;
    }
}
