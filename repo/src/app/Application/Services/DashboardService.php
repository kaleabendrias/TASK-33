<?php

namespace App\Application\Services;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use App\Domain\Models\User;

/**
 * Aggregates dashboard statistics for the current user. Centralised so the
 * Livewire component does not query Eloquent directly.
 */
class DashboardService
{
    public function statsFor(User $user): array
    {
        $data = [
            'role'       => $user->role,
            'user'       => $user,
            'totalItems' => BookableItem::where('is_active', true)->count(),
        ];

        if ($user->isAtLeast('staff')) {
            $data['todayOrders']  = Order::whereDate('created_at', today())->count();
            $data['activeOrders'] = Order::whereIn('status', ['confirmed', 'checked_in'])->count();
            $data['monthRevenue'] = Order::whereMonth('confirmed_at', now()->month)
                ->whereIn('status', ['completed', 'checked_out', 'confirmed', 'checked_in'])
                ->sum('total');
        }

        if ($user->isAtLeast('group-leader')) {
            $data['myOrders'] = Order::where('group_leader_id', $user->id)
                ->whereMonth('confirmed_at', now()->month)->count();
            $data['myCommissions'] = Commission::where('group_leader_id', $user->id)
                ->whereIn('status', ['approved', 'paid'])
                ->sum('commission_amount');
        }

        if ($user->isAdmin()) {
            $data['pendingSettlements'] = Settlement::where('status', 'draft')->count();
            $data['totalUsers'] = User::count();
        }

        return $data;
    }
}
