<?php

namespace App\Application\Services;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use App\Domain\Models\User;
use Carbon\Carbon;

/**
 * Aggregates dashboard statistics for the current user. Centralised so the
 * Livewire component does not query Eloquent directly.
 *
 * Date-range stats accept an optional [from, to] window. When omitted, the
 * service falls back to "current month" so legacy callers stay coherent —
 * but the UI now passes an explicit range so group leaders can audit any
 * period, not just the calendar month.
 */
class DashboardService
{
    public function statsFor(User $user, ?string $from = null, ?string $to = null): array
    {
        [$rangeStart, $rangeEnd] = $this->resolveRange($from, $to);

        $data = [
            'role'       => $user->role,
            'user'       => $user,
            'totalItems' => BookableItem::where('is_active', true)->count(),
            'range_from' => $rangeStart->toDateString(),
            'range_to'   => $rangeEnd->toDateString(),
        ];

        if ($user->isAtLeast('staff')) {
            $data['todayOrders']  = Order::whereDate('created_at', today())->count();
            $data['activeOrders'] = Order::whereIn('status', ['confirmed', 'checked_in'])->count();
            // Renamed from monthRevenue but the blade still aliases it for
            // backwards-compatible card rendering.
            $data['rangeRevenue'] = (float) Order::query()
                ->whereBetween('confirmed_at', [$rangeStart, $rangeEnd])
                ->whereIn('status', ['completed', 'checked_out', 'confirmed', 'checked_in'])
                ->sum('total');
            $data['monthRevenue'] = $data['rangeRevenue'];
        }

        if ($user->isAtLeast('group-leader')) {
            $data['myOrders'] = Order::where('group_leader_id', $user->id)
                ->whereBetween('confirmed_at', [$rangeStart, $rangeEnd])
                ->count();
            $data['myCommissions'] = (float) Commission::where('group_leader_id', $user->id)
                ->whereIn('status', ['approved', 'paid'])
                ->where('cycle_start', '>=', $rangeStart->toDateString())
                ->where('cycle_end', '<=', $rangeEnd->toDateString())
                ->sum('commission_amount');
        }

        if ($user->isAdmin()) {
            $data['pendingSettlements'] = Settlement::where('status', 'draft')->count();
            $data['totalUsers'] = User::count();
        }

        return $data;
    }

    /**
     * Parse the inbound from/to strings into a [Carbon, Carbon] tuple.
     * Invalid or missing input falls back to the current calendar month
     * so the dashboard always renders SOMETHING coherent. The end of the
     * range is inclusive (end-of-day) so a single-day query covers
     * orders confirmed at any hour of that day.
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        try {
            $start = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }
        try {
            $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            $end = now()->endOfDay();
        }
        if ($end->lt($start)) {
            // Reject inverted ranges by clamping — never silently swap.
            $end = $start->copy()->endOfDay();
        }
        return [$start, $end];
    }
}
