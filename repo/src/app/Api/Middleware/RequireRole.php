<?php

namespace App\Api\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $user = $request->attributes->get('auth_user');

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Authentication required.'], 401);
            }
            return redirect('/login');
        }

        if (!$user->isAtLeast($minimumRole)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            return redirect('/dashboard')->with('flash_error', 'You do not have permission to access that page.');
        }

        return $next($request);
    }
}
