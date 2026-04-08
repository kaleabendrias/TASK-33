<?php

namespace App\Application\Services;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Application\Services\PricingResolver;
use App\Domain\Models\BookableItem;
use App\Domain\Models\Coupon;
use App\Domain\Models\GroupLeaderAssignment;
use App\Domain\Models\Order;
use App\Domain\Models\OrderLineItem;
use App\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $audit,
        private readonly PricingResolver $pricing = new PricingResolver(),
    ) {}

    /**
     * Catalog read API for the Livewire UI: paginated, filtered list of active
     * bookable items. Centralised here so views never query Eloquent directly.
     */
    public function listActiveItems(?string $search = null, ?string $type = null, int $perPage = 12)
    {
        return BookableItem::query()
            ->where('is_active', true)
            ->when($search, fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /** Check availability for a bookable item at a given date/time. */
    public function checkAvailability(int $itemId, string $date, ?string $startTime, ?string $endTime, int $qty = 1): array
    {
        $item = BookableItem::findOrFail($itemId);
        $available = $item->hasAvailability($date, $startTime, $endTime, $qty);

        return [
            'available' => $available,
            'item'      => $item,
            'conflicts' => $available ? [] : ['This slot is fully booked.'],
        ];
    }

    /** Detect all conflicts for a set of proposed line items. */
    public function detectConflicts(array $lineItems): array
    {
        $conflicts = [];
        foreach ($lineItems as $i => $li) {
            // Reject zero-duration or inverted slots: end_time must be strictly after start_time.
            $start = $li['start_time'] ?? null;
            $end   = $li['end_time'] ?? null;
            if ($start && $end && strtotime($end) <= strtotime($start)) {
                $conflicts[$i] = 'end_time must be strictly after start_time.';
                continue;
            }

            $item = BookableItem::find($li['bookable_item_id']);
            if (!$item) { $conflicts[$i] = 'Item not found.'; continue; }
            if (!$item->hasAvailability($li['booking_date'], $start, $end, $li['quantity'] ?? 1)) {
                $conflicts[$i] = "{$item->name} is unavailable for this slot.";
            }
        }
        return $conflicts;
    }

    /** Validate a coupon against subtotal. */
    public function validateCoupon(string $code, float $subtotal): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
        if (!$coupon) return ['valid' => false, 'error' => 'Coupon not found.'];

        $check = $coupon->isValid($subtotal);
        if ($check !== true) return ['valid' => false, 'error' => $check];

        return [
            'valid'    => true,
            'coupon'   => $coupon,
            'discount' => $coupon->calculateDiscount($subtotal),
        ];
    }

    /** Calculate pricing totals for a set of line items + optional coupon.
     *
     *  $context (optional) feeds the pricing resolver:
     *      member_tier, package_code, headcount
     */
    public function calculateTotals(array $lineItems, ?string $couponCode = null, array $context = []): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        $lines = [];

        foreach ($lineItems as $li) {
            $item = BookableItem::findOrFail($li['bookable_item_id']);
            $qty = $li['quantity'] ?? 1;

            // 1) Compute the BASE unit price from the item's intrinsic pricing.
            if ($item->isConsumable()) {
                $basePrice = (float) $item->unit_price;
            } elseif (!empty($li['start_time']) && !empty($li['end_time'])) {
                $hours = (strtotime($li['end_time']) - strtotime($li['start_time'])) / 3600;
                $basePrice = (float) $item->hourly_rate * max($hours, 0);
            } else {
                $basePrice = (float) $item->daily_rate;
            }

            // 2) Resolve via the multi-dimensional rule engine. Per-line context
            //    inherits from the order-level context but a line may override.
            $lineCtx = array_merge($context, [
                'date'         => $li['booking_date'],
                'start_time'   => $li['start_time'] ?? null,
                'end_time'     => $li['end_time'] ?? null,
                'headcount'    => $li['headcount'] ?? ($context['headcount'] ?? $qty),
                'member_tier'  => $li['member_tier'] ?? ($context['member_tier'] ?? null),
                'package_code' => $li['package_code'] ?? ($context['package_code'] ?? null),
            ]);
            $resolved = $this->pricing->resolveUnitPrice($item, $basePrice, $lineCtx);
            $unitPrice = $resolved['unit_price'];
            $appliedRule = $resolved['rule'];

            $lineSubtotal = round($unitPrice * $qty, 2);
            $lineTax = round($lineSubtotal * (float) $item->tax_rate, 2);

            $lines[] = [
                'bookable_item_id' => $item->id,
                'item_name'        => $item->name,
                'booking_date'     => $li['booking_date'],
                'start_time'       => $li['start_time'] ?? null,
                'end_time'         => $li['end_time'] ?? null,
                'quantity'         => $qty,
                'unit_price'       => $unitPrice,
                'tax_rate'         => (float) $item->tax_rate,
                'line_subtotal'    => $lineSubtotal,
                'line_tax'         => $lineTax,
                'line_total'       => $lineSubtotal + $lineTax,
                'pricing_rule_id'  => $appliedRule?->id,
            ];

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        $discount = 0;
        $couponId = null;
        if ($couponCode) {
            $cv = $this->validateCoupon($couponCode, $subtotal);
            if ($cv['valid']) {
                $discount = $cv['discount'];
                $couponId = $cv['coupon']->id;
            }
        }

        return [
            'lines'     => $lines,
            'subtotal'  => round($subtotal, 2),
            'tax_amount' => round($taxTotal, 2),
            'discount'  => round($discount, 2),
            'total'     => round($subtotal + $taxTotal - $discount, 2),
            'coupon_id' => $couponId,
        ];
    }

    /** Create an order with line items. */
    public function createOrder(int $userId, array $lineItems, ?int $groupLeaderId = null, ?int $serviceAreaId = null, ?string $couponCode = null, ?string $notes = null): Order
    {
        // Validate: a referenced group leader must actually hold that role + be active.
        if ($groupLeaderId !== null) {
            $leader = User::find($groupLeaderId);
            if (!$leader || !in_array($leader->role, ['group-leader', 'admin'], true) || !$leader->is_active) {
                throw ValidationException::withMessages([
                    'group_leader_id' => 'Assigned user is not an eligible group leader.',
                ]);
            }

            // Enforce GroupLeaderAssignment: the leader must hold an active assignment
            // covering the order's service area. Admins bypass this check.
            // The check is skipped only if no service area is specified (free-form order).
            if ($leader->role !== 'admin' && $serviceAreaId !== null) {
                $assignment = GroupLeaderAssignment::where('user_id', $leader->id)
                    ->where('service_area_id', $serviceAreaId)
                    ->where('is_active', true)
                    ->first();
                if (!$assignment) {
                    throw ValidationException::withMessages([
                        'group_leader_id' => 'Group leader has no active assignment for this service area.',
                    ]);
                }

                // Location binding (previously ignored despite both
                // bookable_items and group_leader_assignments carrying a
                // `location` column). Semantics:
                //   - Assignment.location NULL/empty  → leader covers all
                //     locations in the service area (no narrowing).
                //   - Assignment.location set         → every line item's
                //     bookable_item.location must match (trim, case-insensitive).
                //     Items with a NULL location are NOT covered by a
                //     location-bound assignment.
                $assignedLocation = $this->normalizeLocation($assignment->location);
                if ($assignedLocation !== null) {
                    $itemIds = array_values(array_unique(array_map(
                        fn ($li) => (int) ($li['bookable_item_id'] ?? 0),
                        $lineItems
                    )));
                    $itemLocations = BookableItem::whereIn('id', $itemIds)
                        ->pluck('location', 'id');
                    foreach ($itemIds as $iid) {
                        $itemLoc = $this->normalizeLocation($itemLocations[$iid] ?? null);
                        if ($itemLoc !== $assignedLocation) {
                            throw ValidationException::withMessages([
                                'group_leader_id' => 'Group leader is not assigned to the location of one or more booked items.',
                            ]);
                        }
                    }
                }
            }
        }

        $conflicts = $this->detectConflicts($lineItems);
        if (!empty($conflicts)) {
            throw ValidationException::withMessages(['conflicts' => array_values($conflicts)]);
        }

        // Build pricing context: user tier flows through the resolver automatically.
        $purchaser = User::find($userId);
        $context = [
            'member_tier' => PricingResolver::tierForUser($purchaser),
        ];

        $totals = $this->calculateTotals($lineItems, $couponCode, $context);

        return DB::transaction(function () use ($userId, $groupLeaderId, $serviceAreaId, $totals, $notes) {
            // Reservations enter the workflow as DRAFT. Customers must explicitly
            // submit-for-approval (draft → pending) and a profile-complete staff
            // member must approve (pending → confirmed). This guarantees no order
            // becomes financially binding without human review.
            $order = Order::create([
                'order_number'    => Order::generateOrderNumber(),
                'user_id'         => $userId,
                'group_leader_id' => $groupLeaderId,
                'service_area_id' => $serviceAreaId,
                'status'          => 'draft',
                'subtotal'        => $totals['subtotal'],
                'tax_amount'      => $totals['tax_amount'],
                'discount_amount' => $totals['discount'],
                'total'           => $totals['total'],
                'coupon_id'       => $totals['coupon_id'],
                'notes'           => $notes,
            ]);

            foreach ($totals['lines'] as $line) {
                // pricing_rule_id is metadata returned to clients but not persisted on the
                // line item table — strip before insert.
                unset($line['pricing_rule_id']);
                OrderLineItem::create(array_merge($line, ['order_id' => $order->id]));
            }

            // Increment coupon usage
            if ($totals['coupon_id']) {
                Coupon::where('id', $totals['coupon_id'])->increment('used_count');
            }

            $this->audit->log('order_created', 'Order', $order->id, null, [
                'total' => $totals['total'], 'items' => count($totals['lines']),
            ]);

            return $order->load('lineItems.bookableItem');
        });
    }

    /** Transition order status with validation. */
    public function transitionOrder(Order $order, string $newStatus, ?string $reason = null): Order
    {
        // Reservation Approval Workflow
        //
        //   draft ──submit──▶ pending ──approve──▶ confirmed
        //                              ╲─reject──▶ cancelled
        //
        // After approval the order joins the existing operational lifecycle.
        $allowed = [
            'draft'       => ['pending', 'cancelled'],
            'pending'     => ['confirmed', 'cancelled'],
            'confirmed'   => ['checked_in', 'cancelled'],
            'checked_in'  => ['checked_out'],
            'checked_out' => ['completed'],
            'completed'   => ['refunded'],
            'cancelled'   => ['refunded'],
        ];

        if (!isset($allowed[$order->status]) || !in_array($newStatus, $allowed[$order->status])) {
            throw ValidationException::withMessages(['status' => "Cannot transition from '{$order->status}' to '{$newStatus}'."]);
        }

        // Inventory bookkeeping for consumables. Wrapped in a single
        // transaction so the status update and the stock movement
        // commit (or roll back) atomically — partial state would let
        // the system oversell on the next read.
        return DB::transaction(function () use ($order, $newStatus, $reason) {
            $previousStatus = $order->status;

            // Decrement on draft → pending. This is the canonical
            // commit point: once the customer has submitted for
            // approval the consumables are off the shelf.
            if ($previousStatus === 'draft' && $newStatus === 'pending') {
                $this->reserveConsumables($order);
            }

            // Restore on cancelled, but only if the previous state had
            // already taken the stock. Going draft → cancelled never
            // touched the pool. Cancelling AFTER refund (or refunded
            // → anything) is excluded by the state machine above.
            if ($newStatus === 'cancelled'
                && in_array($previousStatus, BookableItem::RESERVING_STATUSES, true)) {
                $this->restoreConsumables($order);
            }

            $ts = match ($newStatus) {
                'pending'     => [], // submitted for approval; no timestamp column
                'confirmed'   => ['confirmed_at' => now()],
                'checked_in'  => ['checked_in_at' => now()],
                'checked_out' => ['checked_out_at' => now()],
                'cancelled'   => ['cancelled_at' => now(), 'cancellation_reason' => $reason],
                default       => [],
            };

            $order->update(array_merge(['status' => $newStatus], $ts));

            $this->audit->log("order_{$newStatus}", 'Order', $order->id, null, ['reason' => $reason]);

            return $order->refresh();
        });
    }

    /**
     * Decrement consumable stock for every line item on this order.
     * Rows are SELECT … FOR UPDATE locked so two concurrent submits
     * cannot oversell against the same starting stock.
     *
     * Aborts with 403 Forbidden if any line item would push stock
     * below zero. 403 (rather than 422) is intentional and matches
     * the test contract: oversell is treated as an authorization
     * failure against the inventory invariant, not a user-correctable
     * validation error — the user has no recourse to "fix" the input.
     */
    private function reserveConsumables(Order $order): void
    {
        foreach ($order->lineItems as $line) {
            $item = BookableItem::lockForUpdate()->find($line->bookable_item_id);
            if (!$item || !$item->isConsumable() || $item->stock === null) {
                continue; // non-tracked / unlimited
            }
            if ($item->stock < $line->quantity) {
                abort(403, "Insufficient stock for {$item->name}: requested {$line->quantity}, available {$item->stock}.");
            }
            $item->decrement('stock', $line->quantity);
        }
    }

    /**
     * Restore consumable stock when an order that had previously
     * reserved inventory transitions to cancelled.
     */
    private function restoreConsumables(Order $order): void
    {
        foreach ($order->lineItems as $line) {
            $item = BookableItem::lockForUpdate()->find($line->bookable_item_id);
            if (!$item || !$item->isConsumable() || $item->stock === null) {
                continue;
            }
            $item->increment('stock', $line->quantity);
        }
    }

    /**
     * Hard-delete (or auto-cancel) draft orders older than the
     * configured TTL. Called by the scheduled console command so
     * abandoned reservations don't permanently hold slot availability.
     *
     * Returns the number of orders cleaned up.
     */
    public function cleanupStaleDrafts(int $maxAgeMinutes = 60): int
    {
        $cutoff = now()->subMinutes($maxAgeMinutes);
        $stale = Order::where('status', 'draft')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($stale as $order) {
            // Drafts have not had stock decremented yet (decrement
            // happens at draft → pending), so we just flip status
            // and move on. Going through transitionOrder keeps the
            // audit log honest.
            $this->transitionOrder($order, 'cancelled', 'Auto-expired stale draft.');
            $count++;
        }
        return $count;
    }

    /**
     * Normalize a location string for binding comparisons. Returns null
     * for blank/whitespace values so callers can distinguish "no
     * location constraint" from a real value.
     */
    private function normalizeLocation(?string $location): ?string
    {
        if ($location === null) return null;
        $trimmed = trim($location);
        if ($trimmed === '') return null;
        return mb_strtolower($trimmed);
    }
}
