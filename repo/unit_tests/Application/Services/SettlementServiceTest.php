<?php

namespace UnitTests\Application\Services;

use App\Application\Services\BookingService;
use App\Application\Services\SettlementService;
use App\Domain\Models\BookableItem;
use App\Domain\Models\GroupLeaderAssignment;
use App\Domain\Models\Order;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\User;
use UnitTests\TestCase;

class SettlementServiceTest extends TestCase
{
    private SettlementService $service;
    private BookingService $booking;
    private User $user;
    private User $leader;
    private ServiceArea $sa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SettlementService::class);
        $this->booking = app(BookingService::class);
        $this->user = User::create(['username' => 'stl_user', 'password' => 'TestPass@12345!', 'full_name' => 'U', 'role' => 'staff']);
        $this->leader = User::create(['username' => 'stl_leader', 'password' => 'TestPass@12345!', 'full_name' => 'L', 'role' => 'group-leader']);
        $this->sa = ServiceArea::create(['name' => 'STL SA', 'slug' => 'stl-sa']);
        // Active assignment so the leader is eligible to earn commissions in this SA
        GroupLeaderAssignment::create([
            'user_id' => $this->leader->id,
            'service_area_id' => $this->sa->id,
            'is_active' => true,
        ]);
    }

    private function bookWithLeader(BookableItem $item, string $date, int $qty = 1): Order
    {
        $order = $this->booking->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => $date, 'quantity' => $qty],
        ], $this->leader->id, $this->sa->id);
        // Walk through the approval workflow so the order becomes confirmed
        // and is eligible for downstream commission/settlement logic.
        $order = $this->booking->transitionOrder($order, 'pending');
        return $this->booking->transitionOrder($order->refresh(), 'confirmed');
    }

    private function makeItem(): BookableItem
    {
        static $n = 0;
        return BookableItem::create([
            'type' => 'room', 'name' => 'StlRoom ' . ++$n, 'daily_rate' => 100,
            'tax_rate' => 0.0000, 'capacity' => 10, 'is_active' => true,
        ]);
    }

    /** Create + approve in one call so refund/settlement tests get a confirmed order. */
    private function bookApproved(BookableItem $item, string $date, int $qty = 1): Order
    {
        $order = $this->booking->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => $date, 'quantity' => $qty],
        ]);
        $order = $this->booking->transitionOrder($order, 'pending');
        return $this->booking->transitionOrder($order->refresh(), 'confirmed');
    }

    // --- Refunds ---

    public function test_full_refund_within_15_minutes(): void
    {
        $item = $this->makeItem();
        $order = $this->bookApproved($item, '2026-08-01');
        // confirmed_at is now() after approval, so within window
        $refund = $this->service->processRefund($order, 'Changed mind');
        $this->assertTrue($refund->is_full_refund);
        $this->assertEquals(0.00, (float) $refund->cancellation_fee);
        $this->assertEquals(100.00, (float) $refund->refund_amount);
    }

    public function test_20_percent_fee_after_15_minutes(): void
    {
        $item = $this->makeItem();
        $order = $this->bookApproved($item, '2026-08-02');
        $order->update(['confirmed_at' => now()->subMinutes(20)]);
        $order->refresh();

        $refund = $this->service->processRefund($order, 'Late cancel');
        $this->assertFalse($refund->is_full_refund);
        $this->assertEquals(20.00, (float) $refund->cancellation_fee);
        $this->assertEquals(80.00, (float) $refund->refund_amount);
    }

    public function test_staff_unavailable_waives_fee(): void
    {
        $item = $this->makeItem();
        $order = $this->bookApproved($item, '2026-08-03');
        $order->update(['confirmed_at' => now()->subHour(), 'staff_marked_unavailable' => true]);
        $order->refresh();

        $refund = $this->service->processRefund($order, 'Staff unavailable');
        $this->assertEquals(0.00, (float) $refund->cancellation_fee);
        $this->assertTrue($refund->staff_unavailable_override);
    }

    public function test_refund_sets_order_status(): void
    {
        $item = $this->makeItem();
        $order = $this->bookApproved($item, '2026-08-04');
        $this->service->processRefund($order);
        $this->assertEquals('refunded', $order->refresh()->status);
    }

    // --- Settlements ---

    public function test_generate_settlement(): void
    {
        $item = $this->makeItem();
        $order = $this->bookApproved($item, '2026-08-05');
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');
        $this->booking->transitionOrder($order->refresh(), 'completed');

        $settlement = $this->service->generateSettlement('2026-01-01', '2026-12-31');
        $this->assertStringStartsWith('STL-', $settlement->reference);
        $this->assertEquals(100.00, (float) $settlement->gross_amount);
        $this->assertEquals('draft', $settlement->status);
    }

    public function test_reconcile_clean(): void
    {
        $item = $this->makeItem();
        $order = $this->bookApproved($item, '2026-08-06');
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');
        $this->booking->transitionOrder($order->refresh(), 'completed');

        $settlement = $this->service->generateSettlement('2026-01-01', '2026-12-31');
        $discrepancies = $this->service->reconcile($settlement);
        $this->assertEmpty($discrepancies);
        $this->assertEquals('reconciled', $settlement->refresh()->status);
    }

    // --- Commissions ---

    public function test_calculate_commissions(): void
    {
        $item = $this->makeItem();
        $order = $this->bookWithLeader($item, '2026-08-07', 2);
        // Order total = 200
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');

        $commissions = $this->service->calculateCommissions('2026-01-01', '2026-12-31');
        $this->assertCount(1, $commissions);
        $this->assertEquals($this->leader->id, $commissions[0]->group_leader_id);
        $this->assertEquals(200.00, (float) $commissions[0]->attributed_revenue);
        $this->assertEquals(20.00, (float) $commissions[0]->commission_amount);
        $this->assertEquals('held', $commissions[0]->status);
        $this->assertNotNull($commissions[0]->hold_until);
    }

    public function test_commission_3_business_day_hold(): void
    {
        $item = $this->makeItem();
        $order = $this->bookWithLeader($item, '2026-08-08', 1);
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');

        $commissions = $this->service->calculateCommissions('2026-01-01', '2026-12-31', 'biweekly');
        $this->assertEquals('biweekly', $commissions[0]->cycle_type);
        // hold_until should be >= 3 days after cycle end
        $this->assertTrue($commissions[0]->hold_until->isAfter(now()));
    }

    public function test_commission_excludes_demoted_group_leader(): void
    {
        // Order is assigned to a group leader who later loses the role.
        $item = $this->makeItem();
        $order = $this->bookWithLeader($item, '2026-08-09', 1);
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');

        // Demote the group leader
        $this->leader->update(['role' => 'staff']);

        $commissions = $this->service->calculateCommissions('2026-01-01', '2026-12-31');
        $this->assertCount(0, $commissions, 'Demoted leader should not earn commission');
    }

    public function test_commission_excludes_inactive_group_leader(): void
    {
        $item = $this->makeItem();
        $order = $this->bookWithLeader($item, '2026-08-10', 1);
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');

        $this->leader->update(['is_active' => false]);

        $commissions = $this->service->calculateCommissions('2026-01-01', '2026-12-31');
        $this->assertCount(0, $commissions);
    }

    public function test_create_order_rejects_leader_without_assignment(): void
    {
        $sa2 = ServiceArea::create(['name' => 'Off SA', 'slug' => 'off-sa']);
        $item = $this->makeItem();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->booking->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-08-13', 'quantity' => 1],
        ], $this->leader->id, $sa2->id);
    }

    public function test_commission_nets_refunded_revenue(): void
    {
        $item = $this->makeItem();
        $order = $this->bookWithLeader($item, '2026-08-11', 2);
        $this->booking->transitionOrder($order, 'checked_in');
        $this->booking->transitionOrder($order->refresh(), 'checked_out');

        // Process partial refund (20% fee window) — late refund
        $order->refresh()->update(['confirmed_at' => now()->subHour()]);
        $this->service->processRefund($order->refresh(), 'late');
        // Restore status so it remains commissionable
        $order->refresh()->update(['status' => 'checked_out']);

        $commissions = $this->service->calculateCommissions('2026-01-01', '2026-12-31');
        // total 200 minus 160 refund = 40 net → 4.00 commission
        $this->assertEquals(40.00, (float) $commissions[0]->attributed_revenue);
        $this->assertEquals(4.00, (float) $commissions[0]->commission_amount);
    }
}
