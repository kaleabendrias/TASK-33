<?php

namespace App\Api\Controllers;

use App\Application\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $tokens = $this->auth->login(
            $request->input('username'),
            $request->input('password'),
            $request,
        );

        return response()->json($tokens);
    }

    public function refresh(Request $request): JsonResponse
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Token required.'], 401);
        }

        $token = substr($header, 7);

        try {
            $tokens = $this->auth->refresh($token, $request);
        } catch (\Throwable) {
            return response()->json(['message' => 'Unable to refresh token.'], 401);
        }

        return response()->json($tokens);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        // Effective permission slugs the UI can use to conditionally
        // render buttons. Admin holds every defined permission
        // implicitly; non-admins get whatever has been granted via
        // role_permissions. The list is the SAME slug set the API
        // middleware enforces, so the UI and the backend cannot
        // disagree about who can do what.
        $permRepo = app(\App\Domain\Contracts\PermissionRepositoryInterface::class);
        if ($user->role === 'admin') {
            $effective = \App\Domain\Models\Permission::pluck('slug')->all();
        } else {
            $effective = $permRepo->permissionsForRole($user->role)->all();
        }

        return response()->json([
            'data' => [
                'id'       => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role'     => $user->role,
                'is_active' => $user->is_active,
                'effective_permissions' => $effective,
            ],
        ]);
    }
}
