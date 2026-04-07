<?php

namespace App\Api\Controllers;

use App\Application\Services\SettlementService;
use App\Domain\Models\Commission;
use App\Domain\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettlementApiController extends Controller
{
    public function __construct(private readonly SettlementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        // Delegate to the application service which encapsulates the row-level
        // scoping rules for admin / group-leader / staff. Centralised so that
        // routes, the Livewire UI, and exports cannot diverge.
        return response()->json($this->service->listSettlementsForUser($user, perPage: 10));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        // Single SQL lookup with the row-level scope baked into the WHERE
        // clause. This is both faster and CORRECT for arbitrarily large
        // result sets — the previous "first 1000 in memory" hack would
        // intermittently 404 authorized rows that happened to fall outside
        // the first page.
        $settlement = $this->service->findScopedSettlementForUser($user, $id);
        if ($settlement === null) {
            return response()->json(['message' => 'Settlement not found.'], 404);
        }
        return response()->json(['data' => $settlement]);
    }

    public function generate(Request $request): JsonResponse
    {
        // cycle_type is now a required, validated input — no more silent
        // weekly default. Operational reports rely on the field being correct
        // for downstream commission calculations and CSV/PDF exports.
        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'cycle_type'   => 'required|string|in:weekly,biweekly',
        ]);

        // generateSettlement atomically creates the settlement AND its attributed
        // commissions (with settlement_id linkage AND the cycle_type), so we
        // don't call calculateCommissions a second time here.
        $settlement = $this->service->generateSettlement(
            $request->input('period_start'),
            $request->input('period_end'),
            $request->input('cycle_type'),
        );

        $discrepancies = $this->service->reconcile($settlement);

        return response()->json([
            'data' => $settlement->refresh(),
            'discrepancies' => $discrepancies,
        ], 201);
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $settlement = Settlement::findOrFail($id);
        $user = $request->attributes->get('auth_user');

        $settlement->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);

        return response()->json(['data' => $settlement->refresh()]);
    }

    public function commissions(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $rows = $this->service->listCommissionsForUser(
            $user,
            $request->query('from'),
            $request->query('to'),
        );
        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }
}
