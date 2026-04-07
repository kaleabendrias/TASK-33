<?php

namespace App\Api\Controllers;

use App\Application\Services\BookingService;
use App\Domain\Models\BookableItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BookingApiController extends Controller
{
    public function __construct(private readonly BookingService $booking) {}

    public function items(Request $request): JsonResponse
    {
        $items = BookableItem::query()
            ->where('is_active', true)
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 12));

        return response()->json($items);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'bookable_item_id' => 'required|exists:bookable_items,id',
            'booking_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'quantity' => 'integer|min:1',
        ]);

        $result = $this->booking->checkAvailability(
            $request->input('bookable_item_id'),
            $request->input('booking_date'),
            $request->input('start_time'),
            $request->input('end_time'),
            $request->input('quantity', 1),
        );

        return response()->json([
            'available' => $result['available'],
            'conflicts' => $result['conflicts'],
        ]);
    }

    public function calculateTotals(Request $request): JsonResponse
    {
        $request->validate([
            'line_items' => 'required|array|min:1',
            'line_items.*.bookable_item_id' => 'required|exists:bookable_items,id',
            'line_items.*.booking_date' => 'required|date',
            'line_items.*.quantity' => 'integer|min:1',
            'coupon_code' => 'nullable|string',
        ]);

        $totals = $this->booking->calculateTotals(
            $request->input('line_items'),
            $request->input('coupon_code'),
        );

        return response()->json($totals);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        return response()->json(
            $this->booking->validateCoupon($request->input('code'), (float) $request->input('subtotal'))
        );
    }
}
