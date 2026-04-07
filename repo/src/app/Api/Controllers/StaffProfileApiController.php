<?php

namespace App\Api\Controllers;

use App\Domain\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StaffProfileApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $profile = StaffProfile::where('user_id', $user->id)->first();

        return response()->json(['data' => $profile, 'is_complete' => $profile?->isComplete() ?? false]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string|max:50',
            'department' => 'required|string|max:150',
            'title' => 'required|string|max:150',
        ]);

        $user = $request->attributes->get('auth_user');

        $profile = StaffProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['employee_id', 'department', 'title']),
        );

        return response()->json(['data' => $profile, 'is_complete' => $profile->isComplete()]);
    }
}
