<?php

namespace ApiTests\Dashboard;

use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use ApiTests\TestCase;

class DashboardStatsApiTest extends TestCase
{
    // ── Authentication guard ───────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
    }

    // ── Response schema ────────────────────────────────────────────────

    public function test_authenticated_user_receives_200_with_base_schema(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/dashboard/stats', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'role',
                    'totalItems',
                    'range_from',
                    'range_to',
                    'user' => ['id', 'username', 'full_name', 'role'],
                ],
            ]);
    }

    public function test_response_does_not_expose_raw_user_model(): void
    {
        // The controller strips the Eloquent model and replaces it with
        // scalar identity fields only. Sensitive columns (password, etc.)
        // must never appear in the serialised response.
        $user = $this->createUser('user');
        $resp = $this->getJson('/api/dashboard/stats', $this->authHeaders($user));
        $resp->assertOk();

        $userData = $resp->json('data.user');
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('username', $userData);
        $this->assertArrayNotHasKey('password', $userData);
    }

    // ── Role-scoped fields ─────────────────────────────────────────────

    public function test_staff_response_includes_operational_counters(): void
    {
        $staff = $this->createUser('staff');
        $resp = $this->getJson('/api/dashboard/stats', $this->authHeaders($staff));
        $resp->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'todayOrders',
                    'activeOrders',
                    'rangeRevenue',
                    'monthRevenue',
                ],
            ]);
    }

    public function test_group_leader_response_includes_commission_fields(): void
    {
        $gl = $this->createUser('group-leader');
        $resp = $this->getJson('/api/dashboard/stats', $this->authHeaders($gl));
        $resp->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'myOrders',
                    'myCommissions',
                ],
            ]);
    }

    public function test_admin_response_includes_admin_counters(): void
    {
        $admin = $this->createUser('admin');
        Settlement::create([
            'reference' => 'STL-DS1', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 500, 'refund_total' => 0, 'net_amount' => 500,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);

        $resp = $this->getJson('/api/dashboard/stats', $this->authHeaders($admin));
        $resp->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pendingSettlements',
                    'totalUsers',
                    'todayOrders',
                    'activeOrders',
                    'rangeRevenue',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $resp->json('data.pendingSettlements'));
    }

    public function test_plain_user_response_excludes_staff_only_counters(): void
    {
        $user = $this->createUser('user');
        $resp = $this->getJson('/api/dashboard/stats', $this->authHeaders($user));
        $resp->assertOk();

        $data = $resp->json('data');
        $this->assertArrayNotHasKey('todayOrders', $data);
        $this->assertArrayNotHasKey('activeOrders', $data);
        $this->assertArrayNotHasKey('pendingSettlements', $data);
        $this->assertArrayNotHasKey('totalUsers', $data);
    }

    // ── Optional date-range window ─────────────────────────────────────

    public function test_accepts_optional_from_to_query_params(): void
    {
        $admin = $this->createUser('admin');
        $resp = $this->getJson(
            '/api/dashboard/stats?from=2026-01-01&to=2026-01-31',
            $this->authHeaders($admin)
        );
        $resp->assertOk()
            ->assertJsonPath('data.range_from', '2026-01-01')
            ->assertJsonPath('data.range_to', '2026-01-31');
    }

    public function test_omitting_range_falls_back_to_current_month(): void
    {
        $admin = $this->createUser('admin');
        $resp = $this->getJson('/api/dashboard/stats', $this->authHeaders($admin));
        $resp->assertOk();

        // range_from and range_to must be present (non-null strings)
        $this->assertNotEmpty($resp->json('data.range_from'));
        $this->assertNotEmpty($resp->json('data.range_to'));
    }

    // ── Revenue accuracy ───────────────────────────────────────────────

    public function test_range_revenue_reflects_orders_in_window(): void
    {
        $staff = $this->createUser('staff');
        // Seed an order whose confirmed_at falls inside the requested window.
        Order::create([
            'order_number' => 'DS-REV-' . mt_rand(),
            'user_id'      => $staff->id,
            'status'       => 'completed',
            'subtotal'     => 100,
            'total'        => 100,
            'confirmed_at' => '2026-06-15 10:00:00',
        ]);

        $resp = $this->getJson(
            '/api/dashboard/stats?from=2026-06-01&to=2026-06-30',
            $this->authHeaders($staff)
        );
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(100, $resp->json('data.rangeRevenue'));
    }
}
