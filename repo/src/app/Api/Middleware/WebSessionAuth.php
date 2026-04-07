<?php

namespace App\Api\Middleware;

use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-based auth wrapper for Livewire web routes.
 * Validates the JWT stored in the session and hydrates the request
 * with auth_user, just like JwtAuthenticate does for API calls.
 */
class WebSessionAuth
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = session('jwt_token');

        if (!$token) {
            return redirect('/login');
        }

        try {
            [$payload, $session] = $this->jwt->validateToken($token);
        } catch (\Throwable) {
            session()->forget(['jwt_token', 'auth_user_id', 'auth_user_name', 'auth_role']);
            return redirect('/login')->with('flash_error', 'Session expired. Please log in again.');
        }

        $user = User::find($payload['sub']);
        if (!$user || !$user->is_active) {
            session()->flush();
            return redirect('/login')->with('flash_error', 'Account unavailable.');
        }

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_session', $session);
        $request->attributes->set('auth_payload', $payload);

        // CRITICAL: hydrate Laravel's auth guard so Gate::allows(), policies, and
        // any code that calls auth()->user() inside Livewire components can find
        // the authenticated user. Without this, OrderPolicy::view() etc. silently
        // receive null and deny everything in the web context.
        Auth::setUser($user);

        return $next($request);
    }
}
