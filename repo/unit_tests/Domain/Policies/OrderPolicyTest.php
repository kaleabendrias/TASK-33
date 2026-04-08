<?php

namespace UnitTests\Domain\Policies;

use App\Domain\Models\Order;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Domain\Policies\OrderPolicy;
use UnitTests\TestCase;

class OrderPolicyTest extends TestCase
{
    private OrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new OrderPolicy();
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'username' => $role . '_' . mt_rand(1000, 9999),
            'password' => 'TestPass@12345!',
            'full_name' => ucfirst($role),
            'role' => $role,
        ]);
    }

    private function makeOrder(User $owner, ?User $gl = null): Order
    {
        return Order::create([
            'order_number' => 'ORD-POL-' . mt_rand(1000, 9999),
            'user_id' => $owner->id,
            'group_leader_id' => $gl?->id,
            'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);
    }

    // --- View policy ---

    public function test_owner_can_view(): void
    {
        $user = $this->makeUser('staff');
        $order = $this->makeOrder($user);
        $this->assertTrue($this->policy->view($user, $order));
    }

    public function test_group_leader_can_view_attributed_order(): void
    {
        $owner = $this->makeUser('staff');
        $gl = $this->makeUser('group-leader');
        $order = $this->makeOrder($owner, $gl);
        $this->assertTrue($this->policy->view($gl, $order));
    }

    public function test_admin_can_view_any(): void
    {
        $owner = $this->makeUser('staff');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($owner);
        $this->assertTrue($this->policy->view($admin, $order));
    }

    public function test_unrelated_user_cannot_view(): void
    {
        $owner = $this->makeUser('staff');
        $other = $this->makeUser('staff');
        $order = $this->makeOrder($owner);
        $this->assertFalse($this->policy->view($other, $order));
    }

    // --- Transition policy: self-service vs operational ---

    public function test_owner_can_self_cancel(): void
    {
        $viewer = $this->makeUser('user');
        $order = $this->makeOrder($viewer);
        $this->assertTrue($this->policy->transition($viewer, $order, 'cancelled'));
    }

    public function test_owner_user_cannot_check_in_own_order(): void
    {
        // Operational transitions are reserved for staff+ with profile
        $viewer = $this->makeUser('user');
        $order = $this->makeOrder($viewer);
        $this->assertFalse($this->policy->transition($viewer, $order, 'checked_in'));
        $this->assertFalse($this->policy->transition($viewer, $order, 'checked_out'));
        $this->assertFalse($this->policy->transition($viewer, $order, 'completed'));
    }

    public function test_staff_with_profile_can_check_in_own_order(): void
    {
        $staff = $this->makeUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'X', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff);
        $this->assertTrue($this->policy->transition($staff, $order, 'checked_in'));
        $this->assertTrue($this->policy->transition($staff, $order, 'checked_out'));
        $this->assertTrue($this->policy->transition($staff, $order, 'completed'));
    }

    public function test_staff_can_approve_any_pending_order_regardless_of_ownership(): void
    {
        // Approval queue model: any staff member with a complete profile may
        // confirm a pending order even if they have no ownership/attribution
        // relationship to it.
        $owner = $this->makeUser('user');
        $unrelatedStaff = $this->makeUser('staff');
        StaffProfile::create([
            'user_id' => $unrelatedStaff->id, 'employee_id' => 'X',
            'department' => 'D', 'title' => 'T',
        ]);

        $order = $this->makeOrder($owner);
        $order->update(['status' => 'pending']);

        $this->assertTrue($this->policy->transition($unrelatedStaff->refresh(), $order->refresh(), 'confirmed'));
    }

    public function test_staff_without_profile_cannot_approve(): void
    {
        $owner = $this->makeUser('user');
        $staff = $this->makeUser('staff'); // no profile
        $order = $this->makeOrder($owner);
        $order->update(['status' => 'pending']);

        $this->assertFalse($this->policy->transition($staff, $order->refresh(), 'confirmed'));
    }

    public function test_staff_cannot_approve_non_pending_order(): void
    {
        // The approval rule unlocks pending → confirmed only. A draft must
        // first transition to pending via the owner before staff can act.
        $owner = $this->makeUser('user');
        $staff = $this->makeUser('staff');
        StaffProfile::create([
            'user_id' => $staff->id, 'employee_id' => 'X',
            'department' => 'D', 'title' => 'T',
        ]);
        $order = $this->makeOrder($owner); // status=confirmed by default
        $this->assertFalse($this->policy->transition($staff, $order, 'confirmed'));
    }

    public function test_staff_with_profile_can_view_pending_order_for_approval(): void
    {
        $owner = $this->makeUser('user');
        $staff = $this->makeUser('staff');
        StaffProfile::create([
            'user_id' => $staff->id, 'employee_id' => 'X',
            'department' => 'D', 'title' => 'T',
        ]);
        $order = $this->makeOrder($owner);
        $order->update(['status' => 'pending']);

        $this->assertTrue($this->policy->view($staff, $order->refresh()));
    }

    public function test_admin_can_perform_any_transition(): void
    {
        $owner = $this->makeUser('staff');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($owner);
        $this->assertTrue($this->policy->transition($admin, $order, 'checked_in'));
        $this->assertTrue($this->policy->transition($admin, $order, 'cancelled'));
        $this->assertTrue($this->policy->transition($admin, $order, 'completed'));
    }

    public function test_viewer_cannot_transition_others_order(): void
    {
        $owner = $this->makeUser('staff');
        $viewer = $this->makeUser('user');
        $order = $this->makeOrder($owner);
        $this->assertFalse($this->policy->transition($viewer, $order, 'checked_in'));
    }

    public function test_staff_without_profile_cannot_transition_others_order(): void
    {
        $owner = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $order = $this->makeOrder($owner);
        $this->assertFalse($this->policy->transition($staff, $order, 'checked_in'));
    }

    public function test_staff_with_profile_can_transition_own(): void
    {
        $staff = $this->makeUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E1', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff);
        $this->assertTrue($this->policy->transition($staff, $order, 'checked_in'));
    }

    public function test_staff_with_profile_cannot_transition_others(): void
    {
        $owner = $this->makeUser('staff');
        $other = $this->makeUser('staff');
        StaffProfile::create(['user_id' => $other->id, 'employee_id' => 'E2', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($owner);
        $this->assertFalse($this->policy->transition($other, $order, 'checked_in'));
    }

    // --- Refund policy ---

    public function test_owner_can_refund(): void
    {
        $staff = $this->makeUser('staff');
        $order = $this->makeOrder($staff);
        // The refund state-gate requires the order to be in
        // 'cancelled' or 'completed' first — refunds are not legal
        // out of an in-flight reservation.
        $order->update(['status' => 'completed']);
        $this->assertTrue($this->policy->refund($staff, $order));
    }

    public function test_owner_cannot_refund_active_order(): void
    {
        // State-gate: a confirmed (live) order MUST NOT be refundable
        // even by an authorized owner — this is the foot-gun that
        // OrderPolicy::REFUNDABLE_STATUSES exists to close.
        $staff = $this->makeUser('staff');
        $order = $this->makeOrder($staff); // default status = 'confirmed'
        $this->assertFalse($this->policy->refund($staff, $order));
    }

    public function test_already_refunded_order_cannot_be_refunded_again(): void
    {
        // Idempotency guard: once `refunded_at` is stamped, the
        // policy denies further refund attempts as a 403.
        $staff = $this->makeUser('staff');
        $order = $this->makeOrder($staff);
        $order->update(['status' => 'completed', 'refunded_at' => now()]);
        $this->assertFalse($this->policy->refund($staff, $order));
    }

    public function test_viewer_cannot_refund(): void
    {
        $viewer = $this->makeUser('user');
        $order = $this->makeOrder($viewer);
        $order->update(['status' => 'completed']);
        $this->assertFalse($this->policy->refund($viewer, $order));
    }

    // --- Mark unavailable policy ---

    public function test_owner_can_mark_unavailable(): void
    {
        $staff = $this->makeUser('staff');
        $order = $this->makeOrder($staff);
        $this->assertTrue($this->policy->markUnavailable($staff, $order));
    }

    public function test_unrelated_staff_cannot_mark_unavailable(): void
    {
        $owner = $this->makeUser('staff');
        $other = $this->makeUser('staff');
        $order = $this->makeOrder($owner);
        $this->assertFalse($this->policy->markUnavailable($other, $order));
    }

    public function test_group_leader_cannot_mark_unavailable_even_for_attributed_order(): void
    {
        // Group leaders are deliberately stripped of the markUnavailable
        // override; it is reserved for the staff/admin operational floor.
        $owner = $this->makeUser('staff');
        $gl = $this->makeUser('group-leader');
        $order = $this->makeOrder($owner, $gl);
        $this->assertFalse($this->policy->markUnavailable($gl, $order));
    }

    public function test_admin_can_mark_unavailable(): void
    {
        $owner = $this->makeUser('staff');
        $admin = $this->makeUser('admin');
        $order = $this->makeOrder($owner);
        $this->assertTrue($this->policy->markUnavailable($admin, $order));
    }

    public function test_regular_user_cannot_mark_unavailable_own_order(): void
    {
        // Even on their own order, a regular user lacks the operational
        // role required for the staff-unavailable override.
        $u = $this->makeUser('user');
        $order = $this->makeOrder($u);
        $this->assertFalse($this->policy->markUnavailable($u, $order));
    }
}
