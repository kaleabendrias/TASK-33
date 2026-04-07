<?php

namespace App\Domain\Policies;

use App\Domain\Models\Order;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;

class OrderPolicy
{
    /**
     * Approval transitions — staff with a complete profile may operate on any
     * pending reservation in their workflow regardless of ownership. This is
     * the operational queue model: tickets land in the staff inbox and any
     * staff member on shift can clear them.
     */
    public const APPROVAL_TRANSITIONS = ['confirmed'];

    /**
     * Can the user view this order?
     *
     *  - Admins → always
     *  - Owners → always (their own order)
     *  - Attributed group-leaders → always
     *  - Staff (with complete profile) → may view any PENDING order so they
     *    can review the queue before approving. Once the order is no longer
     *    pending, the narrower ownership/attribution rules kick back in.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;
        if ($order->user_id === $user->id) return true;
        if ($order->group_leader_id === $user->id) return true;
        if ($order->status === 'pending'
            && $user->role === 'staff'
            && $this->hasCompleteProfile($user)) {
            return true;
        }
        return false;
    }

    /**
     * Operational transitions that physically move an order through fulfilment.
     * Reserved for staff+ with a complete profile AND ownership/attribution.
     * 'confirmed' is NOT in this set anymore — it lives in APPROVAL_TRANSITIONS
     * because approval is queue-based, not ownership-based.
     */
    public const OPERATIONAL_TRANSITIONS = ['checked_in', 'checked_out', 'completed'];

    /**
     * Self-service transitions that owners may perform on their own orders.
     *  - 'pending'   : owner submits a draft for staff approval.
     *  - 'cancelled' : owner cancels their own order.
     */
    public const SELF_SERVICE_TRANSITIONS = ['pending', 'cancelled'];

    /**
     * Can the user transition this order's status to $newStatus?
     *
     *  - Approval (pending → confirmed): any staff member with a complete
     *    profile, regardless of ownership. The pending queue is a shared
     *    workflow inbox, not a per-staff silo. Admins are also allowed.
     *  - Other operational (check_in/check_out/complete): staff+ with complete
     *    profile AND object access (admin OR owner OR attributed group-leader).
     *  - Self-service (pending submission, cancel): owners only.
     *  - Anything else: deny.
     */
    public function transition(User $user, Order $order, string $newStatus): bool
    {
        // Approval queue: any staff-with-profile (or admin) can clear any
        // pending reservation. We do NOT call canActOn() here because the
        // approval queue is intentionally NOT scoped by ownership.
        if (in_array($newStatus, self::APPROVAL_TRANSITIONS, true)) {
            if ($user->isAdmin()) return true;
            if ($user->role !== 'staff') return false;
            if (!$this->hasCompleteProfile($user)) return false;
            // Approval is only valid OUT of 'pending'. The state machine in
            // BookingService enforces this independently, but we double-check
            // here so a stray confirm against a non-pending order is denied
            // by authorization rather than reaching the transition validator.
            return $order->status === 'pending';
        }

        // Other operational transitions: ownership/attribution still required.
        if (in_array($newStatus, self::OPERATIONAL_TRANSITIONS, true)) {
            if (!$user->isAtLeast('staff')) return false;
            if (!$this->hasCompleteProfile($user)) return false;
            return $this->canActOn($user, $order);
        }

        // Self-service transitions: owner only
        if (in_array($newStatus, self::SELF_SERVICE_TRANSITIONS, true)) {
            if ($user->isAdmin()) return true;
            return $order->user_id === $user->id;
        }

        // Any other transition is reserved for admin
        return $user->isAdmin();
    }

    /**
     * Can the user process a refund?
     * Staff+ with object access.
     */
    public function refund(User $user, Order $order): bool
    {
        if (!$user->isAtLeast('staff')) return false;
        return $this->canActOn($user, $order);
    }

    /**
     * Can the user mark an order as staff-unavailable?
     *
     * Restricted to the *operational* roles only — explicit 'staff' or
     * 'admin'. Group-leaders are deliberately excluded even though they
     * sit higher in the role hierarchy: marking an order unavailable is
     * a fulfilment-floor action, not a supervisory one, and the
     * accountability trail must point at the staff member who
     * physically observed the issue.
     */
    public function markUnavailable(User $user, Order $order): bool
    {
        if (!in_array($user->role, ['staff', 'admin'], true)) {
            return false;
        }
        return $this->canActOn($user, $order);
    }

    private function canActOn(User $user, Order $order): bool
    {
        if ($user->isAdmin()) return true;
        if ($order->user_id === $user->id) return true;
        if ($order->group_leader_id === $user->id) return true;
        return false;
    }

    private function hasCompleteProfile(User $user): bool
    {
        if ($user->isAdmin()) return true;
        $profile = StaffProfile::where('user_id', $user->id)->first();
        return $profile && $profile->isComplete();
    }
}
