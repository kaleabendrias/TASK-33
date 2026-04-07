<?php

namespace App\Api\Middleware;

use App\Domain\Contracts\PermissionRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature / button-level permission middleware.
 *
 * Usage: ->middleware('permission:resources.create')
 */
class RequirePermission
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $permissionSlug): Response
    {
        $user = $request->attributes->get('auth_user');

        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (!$this->permissions->roleHasPermission($user->role, $permissionSlug)) {
            return response()->json([
                'message' => "Forbidden: missing permission '{$permissionSlug}'.",
            ], 403);
        }

        return $next($request);
    }
}
