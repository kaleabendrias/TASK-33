<?php

namespace App\Application\Services;

use App\Domain\Models\Order;
use App\Domain\Models\User;

/**
 * Read-side application service for orders. Encapsulates tenant scoping rules
 * so that Livewire components and controllers do not duplicate Eloquent queries
 * (and thus cannot accidentally bypass isolation).
 */
class OrderQueryService
{
    /**
     * Paginated order listing for the given user. Admins see everything;
     * non-admins see only orders they own or are attributed to as group leader.
     */
    public function listForUser(User $user, ?string $statusFilter = null, ?string $search = null, int $perPage = 15)
    {
        return Order::with(['user', 'serviceArea'])
            ->when(!$user->isAdmin(), fn ($q) => $q->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('group_leader_id', $user->id);
            }))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($search, fn ($q) => $q->where('order_number', 'ilike', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /** Eager-loaded order detail for the show page. Authorisation must be enforced separately by Gate. */
    public function findWithDetail(int $id): Order
    {
        return Order::with(['lineItems.bookableItem', 'user', 'groupLeader', 'coupon', 'refunds'])
            ->findOrFail($id);
    }
}
