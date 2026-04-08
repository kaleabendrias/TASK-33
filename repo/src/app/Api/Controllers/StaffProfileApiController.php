<?php

namespace App\Api\Controllers;

use App\Domain\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StaffProfileApiController extends Controller
{
    /**
     * Roles permitted to OWN a staff profile row. Customers (role
     * 'user') have no operational identity to attach a profile to,
     * and allowing them to create rows would corrupt the staff
     * directory and let arbitrary accounts pose as on-shift staff.
     */
    private const PROFILE_ELIGIBLE_ROLES = ['staff', 'group-leader', 'admin'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $profile = StaffProfile::where('user_id', $user->id)->first();

        return response()->json(['data' => $profile, 'is_complete' => $profile?->isComplete() ?? false]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        // Hard data-integrity gate: only operational roles may have a
        // profile row at all. The check covers both branches —
        // create (no row yet) and update — so a future role demotion
        // can't leave a stale 'user' account editing staff fields.
        if (!in_array($user->role, self::PROFILE_ELIGIBLE_ROLES, true)) {
            return response()->json([
                'message' => 'Only staff, group-leader, and admin roles may have a profile.',
            ], 403);
        }

        $request->validate([
            'employee_id' => 'required|string|max:50',
            'department' => 'required|string|max:150',
            'title' => 'required|string|max:150',
        ]);

        $profile = StaffProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['employee_id', 'department', 'title']),
        );

        return response()->json(['data' => $profile, 'is_complete' => $profile->isComplete()]);
    }
}
