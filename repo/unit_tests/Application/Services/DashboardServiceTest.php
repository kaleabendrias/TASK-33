<?php

namespace UnitTests\Application\Services;

use App\Application\Services\DashboardService;
use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use App\Domain\Models\User;
use UnitTests\TestCase;

class DashboardServiceTest extends TestCase
{
    private DashboardService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(DashboardService::class);
        BookableItem::create([
            'type' => 'room', 'name' => 'D Room', 'daily_rate' => 100,
            'tax_rate' => 0.0, 'capacity' => 5, 'is_active' => true,
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'username' => "dash_$role" . mt_rand(),
            'password' => 'TestPass@12345!',
            'full_name' => $role,
            'role' => $role,
        ]);
    }

    public function test_user_role_basic_stats(): void
    {
        $u = $this->makeUser('user');
        $stats = $this->svc->statsFor($u);
        $this->assertArrayHasKey('totalItems', $stats);
        $this->assertEquals('user', $stats['role']);
        $this->assertArrayNotHasKey('todayOrders', $stats);
    }

    public function test_staff_sees_today_orders(): void
    {
        $u = $this->makeUser('staff');
        Order::create([
            'order_number' => 'D-' . mt_rand(), 'user_id' => $u->id,
            'status' => 'confirmed', 'subtotal' => 50, 'total' => 50,
            'confirmed_at' => now(),
        ]);
        $stats = $this->svc->statsFor($u);
        $this->assertArrayHasKey('todayOrders', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['todayOrders']);
    }

    public function test_group_leader_sees_attributed_metrics(): void
    {
        $gl = $this->makeUser('group-leader');
        $u = $this->makeUser('user');
        Order::create([
            'order_number' => 'D-GL-' . mt_rand(), 'user_id' => $u->id,
            'group_leader_id' => $gl->id, 'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);
        $stats = $this->svc->statsFor($gl);
        $this->assertArrayHasKey('myOrders', $stats);
        $this->assertArrayHasKey('myCommissions', $stats);
    }

    public function test_admin_sees_pending_settlements_and_user_count(): void
    {
        $a = $this->makeUser('admin');
        $stats = $this->svc->statsFor($a);
        $this->assertArrayHasKey('pendingSettlements', $stats);
        $this->assertArrayHasKey('totalUsers', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['totalUsers']);
    }

    public function test_dashboard_range_revenue_respects_custom_window(): void
    {
        // Two staff orders: one inside the explicit window, one outside.
        // The custom-range total must include only the in-window order,
        // proving that the dashboard now honours the from/to parameters
        // instead of silently clamping to the calendar month.
        $staff = $this->makeUser('staff');

        Order::create([
            'order_number' => 'D-IN-' . mt_rand(),
            'user_id' => $staff->id,
            'status' => 'completed',
            'subtotal' => 200, 'total' => 200,
            'confirmed_at' => '2026-02-15 10:00:00',
        ]);
        Order::create([
            'order_number' => 'D-OUT-' . mt_rand(),
            'user_id' => $staff->id,
            'status' => 'completed',
            'subtotal' => 999, 'total' => 999,
            'confirmed_at' => '2026-03-15 10:00:00',
        ]);

        $stats = $this->svc->statsFor($staff, '2026-02-01', '2026-02-28');

        $this->assertSame('2026-02-01', $stats['range_from']);
        $this->assertSame('2026-02-28', $stats['range_to']);
        $this->assertEqualsWithDelta(200.0, (float) $stats['rangeRevenue'], 0.001,
            'Range revenue must only sum orders inside the explicit window');
    }

    public function test_dashboard_range_inverted_input_is_clamped(): void
    {
        // An end date before the start date must not silently swap the
        // bounds — that would leak data outside what the operator asked
        // for. The service clamps end := start (inclusive) instead.
        $staff = $this->makeUser('staff');
        $stats = $this->svc->statsFor($staff, '2026-02-10', '2026-02-01');
        $this->assertSame('2026-02-10', $stats['range_from']);
        $this->assertSame('2026-02-10', $stats['range_to']);
    }
}
