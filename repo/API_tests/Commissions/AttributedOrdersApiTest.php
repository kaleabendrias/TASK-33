<?php

namespace ApiTests\Commissions;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use ApiTests\TestCase;

class AttributedOrdersApiTest extends TestCase
{
    // ── Authentication guard ───────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/commissions/attributed-orders')->assertStatus(401);
    }

    // ── Role-based access control ──────────────────────────────────────

    public function test_plain_user_is_forbidden(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_staff_without_leader_role_is_forbidden(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        // The route middleware only grants access to staff+, but the controller
        // further restricts the action to admin OR group-leader.
        $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($staff))
            ->assertStatus(403);
    }

    public function test_group_leader_receives_200(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($gl))
            ->assertOk();
    }

    public function test_admin_receives_200(): void
    {
        $admin = $this->createUser('admin');
        $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($admin))
            ->assertOk();
    }

    // ── Response schema ────────────────────────────────────────────────

    public function test_response_schema_contains_data_and_total(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($gl))
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_empty_result_when_no_attributed_orders(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $resp = $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($gl));
        $resp->assertOk();
        $this->assertEquals(0, $resp->json('total'));
        $this->assertIsArray($resp->json('data'));
    }

    // ── Row-level isolation ────────────────────────────────────────────

    public function test_group_leader_only_sees_own_attributed_orders(): void
    {
        $gl1 = $this->createStaffWithProfile('group-leader');
        $gl2 = $this->createStaffWithProfile('group-leader');

        // Seed an order attributed to gl1.
        $order = Order::create([
            'order_number'    => 'ATTR-ISO-' . mt_rand(),
            'user_id'         => $gl1->id,
            'group_leader_id' => $gl1->id,
            'status'          => 'completed',
            'subtotal'        => 200,
            'total'           => 200,
            'confirmed_at'    => now(),
        ]);

        // gl2 should not see gl1's order.
        $resp = $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($gl2));
        $resp->assertOk();

        $ids = collect($resp->json('data'))->pluck('id')->all();
        $this->assertNotContains($order->id, $ids, 'gl2 must not see orders attributed to gl1');
    }

    public function test_group_leader_sees_own_attributed_orders(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');

        Order::create([
            'order_number'    => 'ATTR-OWN-' . mt_rand(),
            'user_id'         => $gl->id,
            'group_leader_id' => $gl->id,
            'status'          => 'completed',
            'subtotal'        => 150,
            'total'           => 150,
            'confirmed_at'    => now(),
        ]);

        $resp = $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($gl));
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, $resp->json('total'));
    }

    // ── Optional date-range filtering ─────────────────────────────────

    public function test_accepts_from_and_to_query_params(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $this->getJson(
            '/api/commissions/attributed-orders?from=2026-01-01&to=2026-12-31',
            $this->authHeaders($gl)
        )->assertOk()->assertJsonStructure(['data', 'total']);
    }

    public function test_date_filter_excludes_orders_outside_window(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');

        // Order with confirmed_at in 2025 — should be excluded when querying 2026.
        Order::create([
            'order_number'    => 'ATTR-OLD-' . mt_rand(),
            'user_id'         => $gl->id,
            'group_leader_id' => $gl->id,
            'status'          => 'completed',
            'subtotal'        => 99,
            'total'           => 99,
            'confirmed_at'    => '2025-06-01 12:00:00',
        ]);

        $resp = $this->getJson(
            '/api/commissions/attributed-orders?from=2026-01-01&to=2026-12-31',
            $this->authHeaders($gl)
        );
        $resp->assertOk();

        // The 2025 order must not appear in the 2026 window.
        $totals = collect($resp->json('data'))->pluck('total')->all();
        foreach ($totals as $t) {
            $this->assertNotEquals(99, (float) $t, 'Order from outside the date window leaked into results');
        }
    }

    // ── Admin sees all attributed orders ──────────────────────────────

    public function test_admin_sees_own_attributed_orders(): void
    {
        // The service scopes by group_leader_id = $user->id for all callers,
        // including admins. An admin sees attributed orders where their own
        // user ID is recorded as the group_leader_id.
        $admin = $this->createUser('admin');

        Order::create([
            'order_number'    => 'ATTR-ADMIN-' . mt_rand(),
            'user_id'         => $admin->id,
            'group_leader_id' => $admin->id,
            'status'          => 'completed',
            'subtotal'        => 300,
            'total'           => 300,
            'confirmed_at'    => now(),
        ]);

        $resp = $this->getJson('/api/commissions/attributed-orders', $this->authHeaders($admin));
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, $resp->json('total'));
    }
}
