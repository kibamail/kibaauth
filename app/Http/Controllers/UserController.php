<?php

namespace App\Http\Controllers;

use App\Helpers\OAuthClientHelper;
use App\Services\User\UserDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserDataService $userDataService
    ) {}

    /**
     * Display the authenticated user with all their workspaces, teams, and related data.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $clientId = OAuthClientHelper::getClientId($request);

        $userData = $this->userDataService->assembleUserData($user, $clientId);

        return response()->json(['data' => $userData]);
    }
}
