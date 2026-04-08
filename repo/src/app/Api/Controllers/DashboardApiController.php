<?php

namespace App\Api\Controllers;

use App\Application\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Dashboard read endpoint. Mirrors DashboardService::statsFor for the
 * authenticated caller. Centralising the read here lets the Livewire
 * dashboard component go through the API instead of touching the
 * service or models directly, keeping authorization and scoping rules
 * uniform with REST consumers.
 */
class DashboardApiController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function stats(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        // Optional date-range window. The Livewire dashboard now passes
        // explicit from/to so users can audit any period; the service
        // falls back to the current calendar month when omitted.
        $data = $this->dashboard->statsFor(
            $user,
            $request->query('from'),
            $request->query('to'),
        );

        // Strip the raw User model from the payload — only the scalar
        // identity fields cross the network.
        unset($data['user']);
        $data['user'] = [
            'id'        => $user->id,
            'username'  => $user->username,
            'full_name' => $user->full_name,
            'role'      => $user->role,
        ];

        return response()->json(['data' => $data]);
    }
}
