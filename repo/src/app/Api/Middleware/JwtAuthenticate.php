<?php

namespace App\Api\Middleware;

use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        // In-process sub-request short-circuit. When the Livewire layer
        // dispatches an internal API call via UsesApiClient, it pre-stamps
        // `auth_user` on the request attributes. There is no JWT to
        // validate (the user is already authenticated upstream by
        // WebSessionAuth) so we accept the pre-stamped identity. This
        // is safe because only in-process code can write to the request
        // attributes — external HTTP clients cannot forge them.
        if ($request->attributes->get('auth_user') instanceof User) {
            $user = $request->attributes->get('auth_user');
            if (!$user->is_active) {
                return response()->json(['message' => 'Authentication required.'], 401);
            }
            app('auth')->setUser($user);
            return $next($request);
        }

        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        $token = substr($header, 7);

        try {
            [$payload, $session] = $this->jwt->validateToken($token);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        $user = User::find($payload['sub']);

        if (!$user || !$user->is_active) {
            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        // Store on request for downstream consumption
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_session', $session);
        $request->attributes->set('auth_payload', $payload);

        // Also set on Laravel's auth guard so Gate/authorize() can find the user
        app('auth')->setUser($user);

        return $next($request);
    }
}
