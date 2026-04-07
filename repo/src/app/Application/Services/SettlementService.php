<?php

namespace App\Application\Services;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Models\Commission;
use App\Domain\Models\GroupLeaderAssignment;
use App\Domain\Models\Order;
use App\Domain\Models\Refund;
use App\Domain\Models\Settlement;
use App\Domain\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    private const CANCELLATION_FEE_RATE = 0.20; // 20%
    private const DISPUTE_HOLD_BUSINESS_DAYS = 3;

    /** Allowed settlement cadences. Anything else is rejected at the boundary. */
    public const ALLOWED_CYCLE_TYPES = ['weekly', 'biweekly'];

    public function __construct(
        private readonly AuditLogRepositoryInterface $audit,
    ) {}

    /**
     * Process a refund for an order.
     * Full refund within 15 min, 20% fee after, waive if staff-marked unavailable.
     */
    public function processRefund(Order $order, ?string $reason = null): Refund
    {
        $originalAmount = (float) $order->total;
        $isFullRefund = $order->isWithinFullRefundWindow();
        $staffOverride = (bool) $order->staff_marked_unavailable;

        if ($isFullRefund || $staffOverride) {
            $fee = 0;
        } else {
            $fee = round($originalAmount * self::CANCELLATION_FEE_RATE, 2);
        }

        $refundAmount = round($originalAmount - $fee, 2);

        $refund = Refund::create([
            'order_id'                   => $order->id,
            'original_amount'            => $originalAmount,
            'cancellation_fee'           => $fee,
            'refund_amount'              => $refundAmount,
            'reason'                     => $reason,
            'is_full_refund'             => $isFullRefund || $staffOverride,
            'staff_unavailable_override' => $staffOverride,
            'status'                     => 'processed',
            'processed_at'               => now(),
        ]);

        $order->update(['status' => 'refunded']);

        $this->audit->log('refund_processed', 'Order', $order->id, null, [
            'refund_amount' => $refundAmount, 'fee' => $fee,
        ]);

        return $refund;
    }

    /**
     * Generate a settlement for a date range with an explicit billing cadence.
     *
     * @param  string  $periodStart  ISO date (YYYY-MM-DD)
     * @param  string  $periodEnd    ISO date (YYYY-MM-DD)
     * @param  string  $cycleType    'weekly' or 'biweekly'. Defaults to weekly
     *                               only because the persisted column has the
     *                               same default; callers SHOULD pass it
     *                               explicitly so reports stay coherent.
     *
     * @throws \InvalidArgumentException if $cycleType is not allowed
     */
    public function generateSettlement(string $periodStart, string $periodEnd, string $cycleType = 'weekly'): Settlement
    {
        if (!in_array($cycleType, self::ALLOWED_CYCLE_TYPES, true)) {
            throw new \InvalidArgumentException(
                "cycle_type must be one of: " . implode(', ', self::ALLOWED_CYCLE_TYPES)
            );
        }

        return DB::transaction(function () use ($periodStart, $periodEnd, $cycleType) {
            $orders = Order::whereBetween('confirmed_at', [$periodStart, $periodEnd . ' 23:59:59'])
                ->whereIn('status', ['completed', 'checked_out'])
                ->get();

            $refunds = Refund::whereHas('order', fn ($q) =>
                $q->whereBetween('confirmed_at', [$periodStart, $periodEnd . ' 23:59:59'])
            )->where('status', 'processed')->get();

            $gross = $orders->sum('total');
            $refundTotal = $refunds->sum('refund_amount');

            $settlement = Settlement::create([
                'reference'    => 'STL-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT),
                'period_start' => $periodStart,
                'period_end'   => $periodEnd,
                'gross_amount' => $gross,
                'refund_total' => $refundTotal,
                'net_amount'   => $gross - $refundTotal,
                'order_count'  => $orders->count(),
                'refund_count' => $refunds->count(),
                'status'       => 'draft',
                'cycle_type'   => $cycleType,
            ]);

            // Generate commissions tied to THIS settlement, propagating the
            // cycle_type so weekly vs biweekly cadences are reported correctly.
            $this->calculateCommissions($periodStart, $periodEnd, $cycleType, $settlement->id);

            $this->audit->log('settlement_generated', 'Settlement', $settlement->id, null, [
                'cycle_type' => $cycleType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            return $settlement;
        });
    }

    /**
     * Calculate commissions for group leaders within a date range.
     */
    public function calculateCommissions(string $cycleStart, string $cycleEnd, string $cycleType = 'weekly', ?int $settlementId = null): array
    {
        if (!in_array($cycleType, self::ALLOWED_CYCLE_TYPES, true)) {
            throw new \InvalidArgumentException(
                "cycle_type must be one of: " . implode(', ', self::ALLOWED_CYCLE_TYPES)
            );
        }

        // Eligible group leader IDs: users currently holding the group-leader (or admin) role
        // AND active. This prevents stale or demoted assignments from earning commission.
        $eligibleLeaderIds = User::query()
            ->whereIn('role', ['group-leader', 'admin'])
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $orders = Order::with('refunds')
            ->whereBetween('confirmed_at', [$cycleStart, $cycleEnd . ' 23:59:59'])
            ->whereIn('status', ['completed', 'checked_out'])
            ->whereNotNull('group_leader_id')
            ->whereIn('group_leader_id', $eligibleLeaderIds)
            ->get()
            ->filter(function (Order $o) {
                // Enforce service-area assignment: a commission accrues only when the
                // group leader holds an active GroupLeaderAssignment for the order's
                // service area. Admins always pass. Orders with no service area are
                // treated as out-of-scope for assignment-based commissions.
                $leader = User::find($o->group_leader_id);
                if ($leader && $leader->role === 'admin') return true;
                if (!$o->service_area_id) return false;
                return GroupLeaderAssignment::where('user_id', $o->group_leader_id)
                    ->where('service_area_id', $o->service_area_id)
                    ->where('is_active', true)
                    ->exists();
            })
            ->groupBy('group_leader_id');

        $holdUntil = $this->addBusinessDays(Carbon::parse($cycleEnd), self::DISPUTE_HOLD_BUSINESS_DAYS);
        $commissions = [];

        foreach ($orders as $leaderId => $leaderOrders) {
            // Only revenue from orders that have actually been settled (refunds netted)
            $revenue = $leaderOrders->sum(function ($o) {
                $refunded = (float) optional($o->refunds)->sum('refund_amount');
                return max(0, (float) $o->total - $refunded);
            });
            if ($revenue <= 0) continue;

            $commission = Commission::updateOrCreate(
                ['group_leader_id' => $leaderId, 'cycle_start' => $cycleStart, 'cycle_end' => $cycleEnd],
                [
                    'settlement_id'      => $settlementId,
                    'cycle_type'         => $cycleType,
                    'attributed_revenue' => $revenue,
                    'commission_rate'    => 0.1000,
                    'commission_amount'  => round($revenue * 0.10, 2),
                    'order_count'        => $leaderOrders->count(),
                    'status'             => 'held',
                    'hold_until'         => $holdUntil,
                ],
            );

            $commissions[] = $commission;
        }

        return $commissions;
    }

    private function addBusinessDays(Carbon $start, int $days): Carbon
    {
        $date = $start->copy();
        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if (!$date->isWeekend()) $added++;
        }
        return $date;
    }

    /**
     * Read-side: list settlements visible to a user with strict row-level scoping.
     *
     *   - admin        → every settlement
     *   - group-leader → settlements where they hold at least one commission row
     *   - staff        → settlements whose period covers at least one order they
     *                    personally placed (Order.user_id = staff.id). This is
     *                    the staff-summary access mandated by the spec, and the
     *                    SQL `whereHas` ensures rows the staff has no link to
     *                    never appear in the result set (no broad leakage).
     *   - regular user → no access (the controller layer enforces this)
     */
    public function listSettlementsForUser(User $user, int $perPage = 10)
    {
        return $this->applyRowLevelScope(
            Settlement::query()->orderByDesc('period_end'),
            $user,
        )->paginate($perPage);
    }

    /**
     * Apply the same role-aware row-level scoping as listSettlementsForUser
     * but to a custom date-bounded query, returning a Collection (not a
     * paginator). Used by the export pipeline so CSV/PDF outputs always
     * match what the user sees on screen.
     */
    public function listSettlementsForExport(User $user, string $from, string $to)
    {
        $q = Settlement::query()
            ->whereBetween('period_start', [$from, $to])
            ->orderByDesc('period_end');

        return $this->applyRowLevelScope($q, $user)->get();
    }

    /**
     * Look up a single settlement by id WITH the row-level scope baked into
     * the SQL. Returns null if the user has no relationship to the row, so
     * the caller can serve a clean 404 instead of leaking existence.
     *
     * This replaces the previous "first 1000 items in memory" approach
     * which silently broke as soon as the result set grew past one page.
     */
    public function findScopedSettlementForUser(User $user, int $id): ?Settlement
    {
        $q = Settlement::query()->whereKey($id);
        return $this->applyRowLevelScope($q, $user)->first();
    }

    /**
     * Apply the role-aware row-level scope to a Settlement query. Centralised
     * here so the index, show, and export endpoints share one source of truth
     * for who can see what.
     */
    private function applyRowLevelScope($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->role === 'group-leader') {
            return $query->whereHas('commissions', fn ($cq) => $cq->where('group_leader_id', $user->id));
        }
        if ($user->role === 'staff') {
            return $query->whereExists(function ($sub) use ($user) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('orders')
                    ->where('orders.user_id', $user->id)
                    ->whereColumn('orders.confirmed_at', '>=', 'settlements.period_start')
                    ->whereColumn('orders.confirmed_at', '<=', \Illuminate\Support\Facades\DB::raw("settlements.period_end + interval '1 day'"));
            });
        }
        // Regular users: SQL-level deny.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Read-side: list commissions visible to a user.
     *
     *   - admin        → every commission
     *   - group-leader → commissions attributed to them
     *   - staff        → commissions on settlements that contain at least one
     *                    of their own orders (so they can see who earned what
     *                    against their bookings, without seeing unrelated rows)
     *   - regular user → none
     */
    public function listCommissionsForUser(User $user, ?string $from = null, ?string $to = null)
    {
        $q = Commission::with('groupLeader')
            ->when($from, fn ($q) => $q->where('cycle_start', '>=', $from))
            ->when($to, fn ($q) => $q->where('cycle_end', '<=', $to))
            ->orderByDesc('cycle_end');

        if ($user->isAdmin()) {
            return $q->get();
        }

        if ($user->role === 'group-leader') {
            return $q->where('group_leader_id', $user->id)->get();
        }

        if ($user->role === 'staff') {
            return $q->whereHas('settlement', function ($sq) use ($user) {
                $sq->whereExists(function ($sub) use ($user) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('orders')
                        ->where('orders.user_id', $user->id)
                        ->whereColumn('orders.confirmed_at', '>=', 'settlements.period_start')
                        ->whereColumn('orders.confirmed_at', '<=', \Illuminate\Support\Facades\DB::raw("settlements.period_end + interval '1 day'"));
                });
            })->get();
        }

        return $q->whereRaw('1 = 0')->get();
    }

    /**
     * Read-side: list orders attributed to a group leader within a date window.
     */
    public function listAttributedOrdersForLeader(User $leader, ?string $from, ?string $to, int $limit = 20)
    {
        return Order::where('group_leader_id', $leader->id)
            ->when($from, fn ($q) => $q->whereDate('confirmed_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('confirmed_at', '<=', $to))
            ->orderByDesc('confirmed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Reconcile: verify all order totals match line items and refunds are accounted.
     */
    public function reconcile(Settlement $settlement): array
    {
        $orders = Order::whereBetween('confirmed_at', [$settlement->period_start, $settlement->period_end->endOfDay()])
            ->whereIn('status', ['completed', 'checked_out'])
            ->with('lineItems')
            ->get();

        $discrepancies = [];
        foreach ($orders as $order) {
            $lineTotal = $order->lineItems->sum('line_total');
            $expected = round($lineTotal - (float) $order->discount_amount, 2);
            if (abs((float) $order->total - $expected) > 0.01) {
                $discrepancies[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'recorded_total' => $order->total,
                    'calculated_total' => $expected,
                    'difference' => round((float) $order->total - $expected, 2),
                ];
            }
        }

        if (empty($discrepancies)) {
            $settlement->update(['status' => 'reconciled']);
        }

        return $discrepancies;
    }
}
