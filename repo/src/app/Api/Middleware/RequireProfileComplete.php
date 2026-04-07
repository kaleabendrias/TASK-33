<?php

namespace App\Api\Middleware;

use App\Domain\Models\StaffProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate: staff must complete profile (employee_id, department, title)
 * before accessing approval / check-in / check-out actions.
 */
class RequireProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');

        if (!$user) {
            return redirect('/login');
        }

        // Admins bypass profile gate; non-staff users don't need profiles
        if ($user->isAdmin() || !$user->isAtLeast('staff')) {
            return $next($request);
        }

        $profile = StaffProfile::where('user_id', $user->id)->first();

        if (!$profile || !$profile->isComplete()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Profile incomplete. Complete employee ID, department, and title first.',
                    'redirect' => '/profile',
                ], 403);
            }
            return redirect('/profile')->with('flash_error', 'Please complete your staff profile before proceeding.');
        }

        return $next($request);
    }
}
