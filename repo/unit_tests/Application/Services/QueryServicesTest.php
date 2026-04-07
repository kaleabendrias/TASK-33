<?php

namespace UnitTests\Application\Services;

use App\Application\Services\BookingService;
use App\Application\Services\OrderQueryService;
use App\Application\Services\SettlementService;
use App\Application\Services\StaffProfileService;
use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use UnitTests\TestCase;

class QueryServicesTest extends TestCase
{
    private function makeUser(string $role): User
    {
        return User::create([
            'username' => "qs_$role" . mt_rand(),
            'password' => 'TestPass@12345!',
            'full_name' => $role,
            'role' => $role,
        ]);
    }

    private function makeOrder(User $owner, ?int $glId = null, string $orderNum = ''): Order
    {
        return Order::create([
            'order_number' => $orderNum ?: 'QS-' . mt_rand(),
            'user_id' => $owner->id,
            'group_leader_id' => $glId,
            'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);
    }

    // ── BookingService.listActiveItems ─────────────────────────────────

    public function test_booking_service_lists_only_active_items(): void
    {
        $svc = app(BookingService::class);
        BookableItem::create([
            'type' => 'room', 'name' => 'OnA', 'daily_rate' => 1, 'tax_rate' => 0.0,
            'capacity' => 1, 'is_active' => true,
        ]);
        BookableItem::create([
            'type' => 'room', 'name' => 'OffA', 'daily_rate' => 1, 'tax_rate' => 0.0,
            'capacity' => 1, 'is_active' => false,
        ]);
        $page = $svc->listActiveItems();
        $names = collect($page->items())->pluck('name');
        $this->assertTrue($names->contains('OnA'));
        $this->assertFalse($names->contains('OffA'));
    }

    public function test_booking_service_search_filter(): void
    {
        $svc = app(BookingService::class);
        BookableItem::create([
            'type' => 'room', 'name' => 'AlphaRoom', 'daily_rate' => 1, 'tax_rate' => 0.0,
            'capacity' => 1, 'is_active' => true,
        ]);
        BookableItem::create([
            'type' => 'room', 'name' => 'BetaRoom', 'daily_rate' => 1, 'tax_rate' => 0.0,
            'capacity' => 1, 'is_active' => true,
        ]);
        $page = $svc->listActiveItems(search: 'Alpha');
        $names = collect($page->items())->pluck('name');
        $this->assertTrue($names->contains('AlphaRoom'));
        $this->assertFalse($names->contains('BetaRoom'));
    }

    public function test_booking_service_type_filter(): void
    {
        $svc = app(BookingService::class);
        BookableItem::create([
            'type' => 'consumable', 'name' => 'Pen', 'unit_price' => 1, 'tax_rate' => 0.0,
            'stock' => 10, 'is_active' => true,
        ]);
        $page = $svc->listActiveItems(type: 'consumable');
        $this->assertGreaterThanOrEqual(1, $page->total());
    }

    // ── OrderQueryService ──────────────────────────────────────────────

    public function test_order_query_isolates_non_admin(): void
    {
        $svc = app(OrderQueryService::class);
        $u1 = $this->makeUser('user');
        $u2 = $this->makeUser('user');
        $this->makeOrder($u1);
        $this->makeOrder($u2);
        $page = $svc->listForUser($u1);
        foreach ($page as $o) {
            $this->assertEquals($u1->id, $o->user_id);
        }
    }

    public function test_order_query_admin_sees_all(): void
    {
        $svc = app(OrderQueryService::class);
        $admin = $this->makeUser('admin');
        $u = $this->makeUser('user');
        $this->makeOrder($u);
        $this->makeOrder($admin);
        $page = $svc->listForUser($admin);
        $this->assertGreaterThanOrEqual(2, $page->total());
    }

    public function test_order_query_filters_by_status_and_search(): void
    {
        $svc = app(OrderQueryService::class);
        $admin = $this->makeUser('admin');
        $o = $this->makeOrder($admin, null, 'SEARCH-ME-' . mt_rand());
        $o->update(['status' => 'completed']);
        $page = $svc->listForUser($admin, statusFilter: 'completed', search: 'SEARCH-ME');
        $this->assertGreaterThanOrEqual(1, $page->total());
    }

    public function test_order_query_find_with_detail(): void
    {
        $svc = app(OrderQueryService::class);
        $u = $this->makeUser('user');
        $o = $this->makeOrder($u);
        $found = $svc->findWithDetail($o->id);
        $this->assertEquals($o->id, $found->id);
        $this->assertTrue($found->relationLoaded('lineItems'));
    }

    // ── SettlementService read-side ────────────────────────────────────

    public function test_settlement_list_admin_sees_all(): void
    {
        $svc = app(SettlementService::class);
        $admin = $this->makeUser('admin');
        Settlement::create([
            'reference' => 'STLA-' . mt_rand(),
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $page = $svc->listSettlementsForUser($admin);
        $this->assertGreaterThanOrEqual(1, $page->total());
    }

    public function test_settlement_list_isolates_group_leaders(): void
    {
        $svc = app(SettlementService::class);
        $gl1 = $this->makeUser('group-leader');
        $gl2 = $this->makeUser('group-leader');
        $stl = Settlement::create([
            'reference' => 'STLB-' . mt_rand(),
            'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        Commission::create([
            'group_leader_id' => $gl2->id, 'settlement_id' => $stl->id,
            'cycle_start' => '2026-02-01', 'cycle_end' => '2026-02-28', 'cycle_type' => 'weekly',
            'attributed_revenue' => 100, 'commission_rate' => 0.1, 'commission_amount' => 10,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-03-05',
        ]);
        $page = $svc->listSettlementsForUser($gl1);
        $refs = collect($page->items())->pluck('reference');
        $this->assertFalse($refs->contains($stl->reference));
    }

    public function test_settlement_list_commissions_for_user(): void
    {
        $svc = app(SettlementService::class);
        $gl = $this->makeUser('group-leader');
        $stl = Settlement::create([
            'reference' => 'STLC-' . mt_rand(),
            'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        Commission::create([
            'group_leader_id' => $gl->id, 'settlement_id' => $stl->id,
            'cycle_start' => '2026-03-01', 'cycle_end' => '2026-03-31', 'cycle_type' => 'weekly',
            'attributed_revenue' => 100, 'commission_rate' => 0.1, 'commission_amount' => 10,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-04-05',
        ]);
        $rows = $svc->listCommissionsForUser($gl, '2026-01-01', '2026-12-31');
        $this->assertCount(1, $rows);
    }

    public function test_settlement_list_for_export_admin_sees_all(): void
    {
        $svc = app(SettlementService::class);
        $admin = $this->makeUser('admin');
        Settlement::create([
            'reference' => 'STL-EXP-' . mt_rand(),
            'period_start' => '2026-04-01', 'period_end' => '2026-04-30',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $rows = $svc->listSettlementsForExport($admin, '2026-04-01', '2026-04-30');
        $this->assertGreaterThanOrEqual(1, $rows->count());
    }

    public function test_settlement_list_for_export_regular_user_empty(): void
    {
        $svc = app(SettlementService::class);
        $u = $this->makeUser('user');
        Settlement::create([
            'reference' => 'STL-EXP-U-' . mt_rand(),
            'period_start' => '2026-05-01', 'period_end' => '2026-05-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $rows = $svc->listSettlementsForExport($u, '2026-05-01', '2026-05-31');
        $this->assertEquals(0, $rows->count());
    }

    public function test_settlement_list_for_export_staff_scoped(): void
    {
        $svc = app(SettlementService::class);
        $staff = $this->makeUser('staff');
        // Settlement covers June; no order from this staff in that window.
        Settlement::create([
            'reference' => 'STL-EXP-S-' . mt_rand(),
            'period_start' => '2026-06-01', 'period_end' => '2026-06-30',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $rows = $svc->listSettlementsForExport($staff, '2026-06-01', '2026-06-30');
        $this->assertEquals(0, $rows->count());

        // Now seed a matching order — staff should see the row.
        $this->makeOrder($staff)->update(['confirmed_at' => '2026-06-15 10:00:00']);
        $rows = $svc->listSettlementsForExport($staff, '2026-06-01', '2026-06-30');
        $this->assertGreaterThanOrEqual(1, $rows->count());
    }

    public function test_find_scoped_settlement_for_admin_returns_row(): void
    {
        $svc = app(SettlementService::class);
        $admin = $this->makeUser('admin');
        $stl = Settlement::create([
            'reference' => 'STL-FND-' . mt_rand(),
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $found = $svc->findScopedSettlementForUser($admin, $stl->id);
        $this->assertNotNull($found);
        $this->assertEquals($stl->id, $found->id);
    }

    public function test_find_scoped_settlement_returns_null_for_unrelated_staff(): void
    {
        $svc = app(SettlementService::class);
        $staff = $this->makeUser('staff');
        $stl = Settlement::create([
            'reference' => 'STL-FND2-' . mt_rand(),
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $this->assertNull($svc->findScopedSettlementForUser($staff, $stl->id));
    }

    public function test_find_scoped_settlement_returns_null_for_regular_user(): void
    {
        $svc = app(SettlementService::class);
        $u = $this->makeUser('user');
        $stl = Settlement::create([
            'reference' => 'STL-FND3-' . mt_rand(),
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $this->assertNull($svc->findScopedSettlementForUser($u, $stl->id));
    }

    public function test_settlement_list_attributed_orders(): void
    {
        $svc = app(SettlementService::class);
        $gl = $this->makeUser('group-leader');
        $u = $this->makeUser('user');
        $this->makeOrder($u, $gl->id);
        $rows = $svc->listAttributedOrdersForLeader($gl, '2026-01-01', '2026-12-31');
        $this->assertGreaterThanOrEqual(1, $rows->count());
    }

    // ── StaffProfileService ────────────────────────────────────────────

    public function test_staff_profile_service_returns_profile(): void
    {
        $svc = app(StaffProfileService::class);
        $u = $this->makeUser('staff');
        $this->assertNull($svc->findForUser($u));
        StaffProfile::create([
            'user_id' => $u->id, 'employee_id' => 'E1',
            'department' => 'D', 'title' => 'T',
        ]);
        $this->assertNotNull($svc->findForUser($u));
    }
}
