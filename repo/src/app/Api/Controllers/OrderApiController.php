<?php

namespace App\Api\Controllers;

use App\Api\Resources\OrderResource;
use App\Application\Services\BookingService;
use App\Application\Services\SettlementService;
use App\Domain\Models\Order;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class OrderApiController extends Controller
{
    public function __construct(
        private readonly BookingService $booking,
        private readonly SettlementService $settlement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');
        $isApprovalQueueViewer = $this->canApproveOrders($user);

        $orders = Order::query()
            // Visibility scope:
            //   admin                       → every order
            //   staff w/ complete profile   → own + attributed AS GROUP-LEADER
            //                                  + any 'pending' order anywhere
            //                                  (the shared approval queue)
            //   anyone else                 → only own + attributed
            ->when(!$user->isAdmin(), fn ($q) => $q->where(function ($q) use ($user, $isApprovalQueueViewer) {
                $q->where('user_id', $user->id)
                  ->orWhere('group_leader_id', $user->id);
                if ($isApprovalQueueViewer) {
                    // The OR branch reflects the operational queue model:
                    // any pending order is fair game for an on-shift staff
                    // member, regardless of who placed it.
                    $q->orWhere('status', 'pending');
                }
            }))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            // The Livewire OrderIndex component sends the ?search query
            // parameter; reject any non-string value at the boundary so
            // we cannot leak SQL through the LIKE pattern, then run a
            // case-insensitive partial match against the order number.
            ->when($request->query('search'), function ($q, $s) {
                $needle = trim((string) $s);
                if ($needle === '') return $q;
                return $q->where('order_number', 'ilike', '%' . $needle . '%');
            })
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 15));

        // Apply the resource transformer to whitelist columns and preserve pagination meta.
        return response()->json([
            'data' => OrderResource::collection($orders->items()),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'per_page'     => $orders->perPage(),
            'total'        => $orders->total(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['lineItems', 'refunds'])->findOrFail($id);
        Gate::authorize('view', $order);

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'line_items' => 'required|array|min:1',
            'line_items.*.bookable_item_id' => 'required|exists:bookable_items,id',
            'line_items.*.booking_date' => 'required|date',
            'line_items.*.quantity' => 'integer|min:1',
            'line_items.*.start_time' => 'nullable|date_format:H:i',
            'line_items.*.end_time' => 'nullable|date_format:H:i|after:line_items.*.start_time',
            'coupon_code' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
            'group_leader_id' => 'nullable|exists:users,id',
            'service_area_id' => 'nullable|exists:service_areas,id',
        ]);

        $user = $request->attributes->get('auth_user');

        $order = $this->booking->createOrder(
            userId: $user->id,
            lineItems: $request->input('line_items'),
            groupLeaderId: $request->input('group_leader_id'),
            serviceAreaId: $request->input('service_area_id'),
            couponCode: $request->input('coupon_code'),
            notes: $request->input('notes'),
        );

        return response()->json([
            'data' => new OrderResource($order->load(['lineItems'])),
            'message' => 'Order created.',
        ], 201);
    }

    public function transition(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,confirmed,checked_in,checked_out,completed,cancelled',
            'reason' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);
        Gate::authorize('transition', [$order, $request->input('status')]);

        $order = $this->booking->transitionOrder($order, $request->input('status'), $request->input('reason'));

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        Gate::authorize('refund', $order);

        $request->validate(['reason' => 'nullable|string|max:500']);

        $refund = $this->settlement->processRefund($order, $request->input('reason'));

        return response()->json(['data' => $refund, 'message' => 'Refund processed.']);
    }

    /**
     * True when the user is on the staff approval roster: explicit 'staff'
     * role with a complete profile. Centralised so the index scope and any
     * future helpers stay aligned with OrderPolicy::transition()'s rules.
     */
    private function canApproveOrders(User $user): bool
    {
        if ($user->role !== 'staff') return false;
        $profile = StaffProfile::where('user_id', $user->id)->first();
        return $profile !== null && $profile->isComplete();
    }

    public function markUnavailable(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        Gate::authorize('markUnavailable', $order);

        $order->update(['staff_marked_unavailable' => true]);

        return response()->json(['message' => 'Order marked as staff unavailable.']);
    }
}
