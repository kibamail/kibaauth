<?php

use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('auth:api')->group(function () {
    Route::apiResource('workspaces', WorkspaceController::class);

    // Nested team routes under workspaces
    Route::apiResource('workspaces.teams', TeamController::class)->except(['create', 'edit']);

    // Team permission sync route
    Route::post('workspaces/{workspace}/teams/{team}/sync-permissions', [TeamController::class, 'syncPermissions']);

    // Team member routes
    Route::post('workspaces/{workspace}/teams/{team}/members', [TeamMemberController::class, 'store']);
});
