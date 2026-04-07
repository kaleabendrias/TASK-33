<?php

namespace App\Api\Controllers;

use App\Application\Services\PricingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Admin-only CRUD for the multi-dimensional pricing rule catalog.
 * Authorisation is enforced at the route layer (`role:admin`).
 */
class PricingRuleController extends Controller
{
    public function __construct(private readonly PricingRuleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rules = $this->service->list($request->only([
            'bookable_item_id', 'member_tier', 'package_code', 'is_active', 'per_page',
        ]));
        return response()->json($rules);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->find($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $rule = $this->service->create($request->all());
        return response()->json(['data' => $rule, 'message' => 'Pricing rule created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = $this->service->update($id, $request->all());
        return response()->json(['data' => $rule, 'message' => 'Pricing rule updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Pricing rule deleted.']);
    }
}
