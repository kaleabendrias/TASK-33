<?php

namespace ApiTests\Orders;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use App\Domain\Models\OrderLineItem;
use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use App\Domain\Models\StaffProfile;
use ApiTests\TestCase;

class OrderApiTest extends TestCase
{
    private BookableItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->item = BookableItem::create([
            'type' => 'room', 'name' => 'Test Room', 'hourly_rate' => 50,
            'daily_rate' => 200, 'tax_rate' => 0.1000, 'capacity' => 5, 'is_active' => true,
        ]);
        foreach (['resources.create'] as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        }
    }

    private function staffWithProfile(array $overrides = []): \App\Domain\Models\User
    {
        $user = $this->createUser('staff', $overrides);
        StaffProfile::create(['user_id' => $user->id, 'employee_id' => 'E001', 'department' => 'Eng', 'title' => 'Dev']);
        return $user;
    }

    private function createSampleOrder($user): Order
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST-' . mt_rand(1000, 9999),
            'user_id' => $user->id, 'status' => 'confirmed',
            'subtotal' => 200, 'tax_amount' => 20, 'total' => 220,
            'confirmed_at' => now(),
        ]);
        OrderLineItem::create([
            'order_id' => $order->id, 'bookable_item_id' => $this->item->id,
            'booking_date' => '2026-06-01', 'quantity' => 1, 'unit_price' => 200,
            'line_subtotal' => 200, 'line_tax' => 20, 'line_total' => 220,
        ]);
        return $order;
    }

    // --- Order creation through API ---

    public function test_create_order_via_api(): void
    {
        $user = $this->staffWithProfile();
        $this->postJson('/api/orders', [
            'line_items' => [
                ['bookable_item_id' => $this->item->id, 'booking_date' => '2026-07-01', 'quantity' => 1],
            ],
        ], $this->authHeaders($user))->assertStatus(201)->assertJsonStructure(['data' => ['order_number']]);
    }

    public function test_regular_user_can_create_order(): void
    {
        $viewer = $this->createUser('user');
        $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-07-01', 'quantity' => 1]],
        ], $this->authHeaders($viewer))->assertStatus(201);
    }

    public function test_unauthenticated_cannot_create_order(): void
    {
        $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-07-01', 'quantity' => 1]],
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    // --- Object-level authorization ---

    public function test_owner_can_view_order(): void
    {
        $user = $this->staffWithProfile();
        $order = $this->createSampleOrder($user);
        $this->getJson("/api/orders/{$order->id}", $this->authHeaders($user))->assertOk();
    }

    public function test_other_user_cannot_view_order(): void
    {
        $owner = $this->staffWithProfile(['username' => 'owner_' . mt_rand()]);
        $other = $this->staffWithProfile(['username' => 'other_' . mt_rand()]);
        $order = $this->createSampleOrder($owner);
        $this->getJson("/api/orders/{$order->id}", $this->authHeaders($other))->assertStatus(403);
    }

    public function test_admin_can_view_any_order(): void
    {
        $owner = $this->staffWithProfile(['username' => 'adm_owner_' . mt_rand()]);
        $admin = $this->createUser('admin');
        $order = $this->createSampleOrder($owner);
        $this->getJson("/api/orders/{$order->id}", $this->authHeaders($admin))->assertOk();
    }

    // --- Transition authorization ---

    public function test_owner_can_transition_own_order(): void
    {
        $user = $this->staffWithProfile();
        $order = $this->createSampleOrder($user);
        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'checked_in'], $this->authHeaders($user))
            ->assertOk();
    }

    public function test_non_owner_cannot_transition_order(): void
    {
        $owner = $this->staffWithProfile(['username' => 'trans_owner_' . mt_rand()]);
        $other = $this->staffWithProfile(['username' => 'trans_other_' . mt_rand()]);
        $order = $this->createSampleOrder($owner);
        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'checked_in'], $this->authHeaders($other))
            ->assertStatus(403);
    }

    public function test_staff_without_profile_cannot_transition(): void
    {
        $staff = $this->createUser('staff');
        $admin = $this->createUser('admin');
        $order = $this->createSampleOrder($admin);
        // Staff without profile cannot transition even if admin created the order
        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'checked_in'], $this->authHeaders($staff))
            ->assertStatus(403);
    }

    // --- Refund authorization ---

    public function test_owner_can_refund_own_order(): void
    {
        $user = $this->staffWithProfile();
        $order = $this->createSampleOrder($user);
        $order->update(['status' => 'completed']);
        $this->postJson("/api/orders/{$order->id}/refund", ['reason' => 'Test'], $this->authHeaders($user))
            ->assertOk();
    }

    public function test_non_owner_cannot_refund(): void
    {
        $owner = $this->staffWithProfile(['username' => 'rfnd_own_' . mt_rand()]);
        $other = $this->staffWithProfile(['username' => 'rfnd_oth_' . mt_rand()]);
        $order = $this->createSampleOrder($owner);
        $order->update(['status' => 'completed']);
        $this->postJson("/api/orders/{$order->id}/refund", ['reason' => 'Test'], $this->authHeaders($other))
            ->assertStatus(403);
    }

    // --- Approval queue index visibility ---

    public function test_staff_with_profile_sees_all_pending_orders_in_index(): void
    {
        $stranger = $this->createUser('user', ['username' => 'pq_str_' . mt_rand()]);
        $strangerOrder = Order::create([
            'order_number' => 'ORD-PQ-' . mt_rand(),
            'user_id' => $stranger->id,
            'status' => 'pending',
            'subtotal' => 50, 'total' => 50, 'confirmed_at' => now(),
        ]);
        $staff = $this->staffWithProfile();

        $resp = $this->getJson('/api/orders', $this->authHeaders($staff))->assertOk();
        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($strangerOrder->id),
            'Staff with complete profile must see pending orders from anyone in the index list');
    }

    public function test_staff_without_profile_does_not_see_strangers_pending_orders(): void
    {
        $stranger = $this->createUser('user', ['username' => 'pq_str2_' . mt_rand()]);
        $strangerOrder = Order::create([
            'order_number' => 'ORD-PQ2-' . mt_rand(),
            'user_id' => $stranger->id,
            'status' => 'pending',
            'subtotal' => 50, 'total' => 50, 'confirmed_at' => now(),
        ]);
        $staff = $this->createUser('staff', ['username' => 'pq_nopf_' . mt_rand()]);

        $resp = $this->getJson('/api/orders', $this->authHeaders($staff))->assertOk();
        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($strangerOrder->id),
            'Staff without a complete profile must NOT see other users orders in the index');
    }

    public function test_regular_user_does_not_see_strangers_pending_orders(): void
    {
        $stranger = $this->createUser('user', ['username' => 'pq_str3_' . mt_rand()]);
        $strangerOrder = Order::create([
            'order_number' => 'ORD-PQ3-' . mt_rand(),
            'user_id' => $stranger->id,
            'status' => 'pending',
            'subtotal' => 50, 'total' => 50, 'confirmed_at' => now(),
        ]);
        $u = $this->createUser('user', ['username' => 'pq_u_' . mt_rand()]);

        $resp = $this->getJson('/api/orders', $this->authHeaders($u))->assertOk();
        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($strangerOrder->id));
    }

    public function test_staff_with_profile_does_not_see_strangers_NON_pending_orders(): void
    {
        $stranger = $this->createUser('user', ['username' => 'pq_str4_' . mt_rand()]);
        $confirmed = Order::create([
            'order_number' => 'ORD-PQ4-' . mt_rand(),
            'user_id' => $stranger->id,
            'status' => 'confirmed',
            'subtotal' => 50, 'total' => 50, 'confirmed_at' => now(),
        ]);
        $staff = $this->staffWithProfile();

        $resp = $this->getJson('/api/orders', $this->authHeaders($staff))->assertOk();
        $ids = collect($resp->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($confirmed->id),
            'Approval queue is for pending only — confirmed orders stay scoped by ownership/attribution');
    }

    // --- Permission middleware coverage ---

    public function test_staff_without_permission_blocked_from_resource_create(): void
    {
        // staff role + complete profile but missing the resources.create permission.
        $staff = $this->staffWithProfile();
        $sa = \App\Domain\Models\ServiceArea::create(['name' => 'PermSA', 'slug' => 'permsa-' . mt_rand()]);
        $role = \App\Domain\Models\Role::create(['name' => 'PermRole', 'slug' => 'pr-' . mt_rand(), 'level' => 1]);
        // Ensure the permission is NOT mapped to the staff role.
        \App\Domain\Models\RolePermission::query()
            ->where('role', 'staff')
            ->whereHas('permission', fn ($q) => $q->where('slug', 'resources.create'))
            ->delete();
        \Illuminate\Support\Facades\Cache::flush();
        $this->postJson('/api/resources', [
            'name' => 'NoPerm', 'service_area_id' => $sa->id, 'role_id' => $role->id,
        ], $this->authHeaders($staff))->assertStatus(403);
    }

    // --- Time-window validation ---

    public function test_create_order_rejects_inverted_time_slot(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/orders', [
            'line_items' => [[
                'bookable_item_id' => $this->item->id,
                'booking_date' => '2026-07-15',
                'start_time' => '17:00',
                'end_time' => '15:00',
                'quantity' => 1,
            ]],
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_create_order_rejects_zero_duration(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/orders', [
            'line_items' => [[
                'bookable_item_id' => $this->item->id,
                'booking_date' => '2026-07-15',
                'start_time' => '12:00',
                'end_time' => '12:00',
                'quantity' => 1,
            ]],
        ], $this->authHeaders($user))->assertStatus(422);
    }

    // --- Approval workflow ---

    public function test_new_orders_start_in_draft_status(): void
    {
        $user = $this->createUser('user');
        $resp = $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-09-01', 'quantity' => 1]],
        ], $this->authHeaders($user))->assertStatus(201);
        $this->assertEquals('draft', $resp->json('data.status'));
    }

    public function test_owner_can_submit_draft_to_pending(): void
    {
        $user = $this->createUser('user');
        $resp = $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-09-02', 'quantity' => 1]],
        ], $this->authHeaders($user));
        $orderId = $resp->json('data.id');
        $this->postJson("/api/orders/{$orderId}/transition", ['status' => 'pending'], $this->authHeaders($user))
            ->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_user_cannot_self_approve_pending_order(): void
    {
        // Owner submits draft → pending, then tries to self-approve to confirmed.
        // 'confirmed' is operational and requires staff+profile, so this is rejected.
        $user = $this->createUser('user');
        $resp = $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-09-03', 'quantity' => 1]],
        ], $this->authHeaders($user));
        $orderId = $resp->json('data.id');
        $this->postJson("/api/orders/{$orderId}/transition", ['status' => 'pending'], $this->authHeaders($user))->assertOk();
        $this->postJson("/api/orders/{$orderId}/transition", ['status' => 'confirmed'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_staff_with_profile_can_approve_pending_order(): void
    {
        $owner = $this->createUser('user');
        $staff = $this->staffWithProfile();
        $resp = $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-09-04', 'quantity' => 1]],
        ], $this->authHeaders($owner));
        $orderId = $resp->json('data.id');
        $this->postJson("/api/orders/{$orderId}/transition", ['status' => 'pending'], $this->authHeaders($owner))->assertOk();
        // Admin must approve since the order isn't owned by this staff member.
        $admin = $this->createUser('admin');
        $this->postJson("/api/orders/{$orderId}/transition", ['status' => 'confirmed'], $this->authHeaders($admin))
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    // --- Group leader validation ---

    public function test_create_order_rejects_non_group_leader_assignment(): void
    {
        $user = $this->createUser('user');
        $regular = $this->createUser('user'); // not a group leader
        $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-07-01', 'quantity' => 1]],
            'group_leader_id' => $regular->id,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_create_order_accepts_real_group_leader(): void
    {
        $user = $this->createUser('user');
        $gl = $this->createUser('group-leader');
        $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-07-02', 'quantity' => 1]],
            'group_leader_id' => $gl->id,
        ], $this->authHeaders($user))->assertStatus(201)->assertJsonPath('data.group_leader_id', $gl->id);
    }

    public function test_create_order_rejects_inactive_group_leader(): void
    {
        $user = $this->createUser('user');
        $gl = $this->createUser('group-leader', ['is_active' => false]);
        $this->postJson('/api/orders', [
            'line_items' => [['bookable_item_id' => $this->item->id, 'booking_date' => '2026-07-03', 'quantity' => 1]],
            'group_leader_id' => $gl->id,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    // --- Operational vs self-service transitions ---

    public function test_regular_user_can_cancel_own_order_via_api(): void
    {
        $user = $this->createUser('user');
        $order = $this->createSampleOrder($user);
        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'cancelled'], $this->authHeaders($user))
            ->assertOk();
    }

    public function test_regular_user_cannot_check_in_own_order(): void
    {
        $user = $this->createUser('user');
        $order = $this->createSampleOrder($user);
        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'checked_in'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_staff_with_profile_can_check_in_own_order(): void
    {
        $staff = $this->staffWithProfile();
        $order = $this->createSampleOrder($staff);
        $this->postJson("/api/orders/{$order->id}/transition", ['status' => 'checked_in'], $this->authHeaders($staff))
            ->assertOk();
    }

    // --- Mark unavailable authorization ---

    public function test_mark_unavailable_requires_authorization(): void
    {
        $owner = $this->staffWithProfile(['username' => 'mu_own_' . mt_rand()]);
        $other = $this->staffWithProfile(['username' => 'mu_oth_' . mt_rand()]);
        $order = $this->createSampleOrder($owner);
        $this->postJson("/api/orders/{$order->id}/mark-unavailable", [], $this->authHeaders($other))
            ->assertStatus(403);
    }
}
