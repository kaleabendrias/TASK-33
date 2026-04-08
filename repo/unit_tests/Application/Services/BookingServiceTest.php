<?php

namespace UnitTests\Application\Services;

use App\Application\Services\BookingService;
use App\Domain\Models\BookableItem;
use App\Domain\Models\Coupon;
use App\Domain\Models\Order;
use App\Domain\Models\User;
use Illuminate\Validation\ValidationException;
use UnitTests\TestCase;

class BookingServiceTest extends TestCase
{
    private BookingService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingService::class);
        $this->user = User::create(['username' => 'bk_user', 'password' => 'TestPass@12345!', 'full_name' => 'B', 'role' => 'staff']);
    }

    private function makeItem(array $o = []): BookableItem
    {
        static $n = 0;
        return BookableItem::create(array_merge([
            'type' => 'room', 'name' => 'Room ' . ++$n, 'hourly_rate' => 50, 'daily_rate' => 300,
            'unit_price' => 0, 'tax_rate' => 0.0800, 'capacity' => 1, 'is_active' => true,
        ], $o));
    }

    /** Walk an order through draft → pending → confirmed for tests that need it confirmed. */
    private function approve(Order $order): Order
    {
        $order = $this->service->transitionOrder($order, 'pending');
        return $this->service->transitionOrder($order->refresh(), 'confirmed');
    }

    private function makeCoupon(array $o = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'BK' . mt_rand(100, 999), 'discount_type' => 'percentage', 'discount_value' => 10,
            'min_order_amount' => 0, 'valid_from' => now()->subDay(), 'is_active' => true,
        ], $o));
    }

    // --- Availability ---

    public function test_check_availability_returns_available(): void
    {
        $item = $this->makeItem();
        $result = $this->service->checkAvailability($item->id, '2026-08-01', '09:00', '12:00');
        $this->assertTrue($result['available']);
        $this->assertEmpty($result['conflicts']);
    }

    public function test_check_availability_returns_conflict(): void
    {
        $item = $this->makeItem(['capacity' => 1]);
        $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-08-02', 'start_time' => '09:00', 'end_time' => '17:00', 'quantity' => 1],
        ]);
        $result = $this->service->checkAvailability($item->id, '2026-08-02', '10:00', '11:00');
        $this->assertFalse($result['available']);
        $this->assertNotEmpty($result['conflicts']);
    }

    // --- Conflict Detection ---

    public function test_detect_conflicts_empty_for_available(): void
    {
        $item = $this->makeItem();
        $conflicts = $this->service->detectConflicts([
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-09-01', 'start_time' => '09:00', 'end_time' => '10:00'],
        ]);
        $this->assertEmpty($conflicts);
    }

    public function test_detect_conflicts_missing_item(): void
    {
        $conflicts = $this->service->detectConflicts([
            ['bookable_item_id' => 99999, 'booking_date' => '2026-09-01'],
        ]);
        $this->assertArrayHasKey(0, $conflicts);
        $this->assertStringContainsString('not found', $conflicts[0]);
    }

    // --- Coupon Validation ---

    public function test_validate_coupon_valid(): void
    {
        $coupon = $this->makeCoupon(['code' => 'VALID10']);
        $result = $this->service->validateCoupon('VALID10', 100);
        $this->assertTrue($result['valid']);
        $this->assertEquals(10.0, $result['discount']);
    }

    public function test_validate_coupon_not_found(): void
    {
        $result = $this->service->validateCoupon('DOESNOTEXIST', 100);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    // --- Price Calculation ---

    public function test_calculate_totals_hourly(): void
    {
        $item = $this->makeItem(['hourly_rate' => 40, 'tax_rate' => 0.1000]);
        $totals = $this->service->calculateTotals([
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '12:00', 'quantity' => 1],
        ]);
        // 3 hours × $40 = $120, tax = $12
        $this->assertEquals(120.00, $totals['subtotal']);
        $this->assertEquals(12.00, $totals['tax_amount']);
        $this->assertEquals(132.00, $totals['total']);
    }

    public function test_calculate_totals_daily(): void
    {
        $item = $this->makeItem(['daily_rate' => 200, 'tax_rate' => 0.0500]);
        $totals = $this->service->calculateTotals([
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-10-01', 'quantity' => 2],
        ]);
        // 2 × $200 = $400, tax = $20
        $this->assertEquals(400.00, $totals['subtotal']);
        $this->assertEquals(20.00, $totals['tax_amount']);
    }

    public function test_calculate_totals_consumable(): void
    {
        $item = $this->makeItem(['type' => 'consumable', 'unit_price' => 15, 'tax_rate' => 0.0000, 'stock' => 100]);
        $totals = $this->service->calculateTotals([
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-10-01', 'quantity' => 4],
        ]);
        $this->assertEquals(60.00, $totals['subtotal']);
        $this->assertEquals(0.00, $totals['tax_amount']);
    }

    public function test_calculate_totals_with_coupon(): void
    {
        $item = $this->makeItem(['daily_rate' => 100, 'tax_rate' => 0.0000]);
        $coupon = $this->makeCoupon(['code' => 'DISC20', 'discount_type' => 'fixed', 'discount_value' => 20]);
        $totals = $this->service->calculateTotals(
            [['bookable_item_id' => $item->id, 'booking_date' => '2026-10-01', 'quantity' => 1]],
            'DISC20'
        );
        $this->assertEquals(100.00, $totals['subtotal']);
        $this->assertEquals(20.00, $totals['discount']);
        $this->assertEquals(80.00, $totals['total']);
    }

    // --- Order Creation ---

    public function test_create_order_starts_in_draft(): void
    {
        $item = $this->makeItem(['daily_rate' => 100, 'tax_rate' => 0.0800]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-11-01', 'quantity' => 1],
        ]);
        $this->assertStringStartsWith('ORD-', $order->order_number);
        // Reservation Approval Workflow: orders are born in DRAFT, not confirmed.
        $this->assertEquals('draft', $order->status);
        $this->assertNull($order->confirmed_at);
        $this->assertEquals(108.00, (float) $order->total);
        $this->assertCount(1, $order->lineItems);
    }

    public function test_approval_workflow_draft_pending_confirmed(): void
    {
        $item = $this->makeItem(['daily_rate' => 50, 'tax_rate' => 0]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-11-04', 'quantity' => 1],
        ]);
        $this->assertEquals('draft', $order->status);

        // Owner submits for approval
        $order = $this->service->transitionOrder($order, 'pending');
        $this->assertEquals('pending', $order->status);

        // Staff approves
        $order = $this->service->transitionOrder($order->refresh(), 'confirmed');
        $this->assertEquals('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
    }

    public function test_draft_cannot_skip_to_checked_in(): void
    {
        $item = $this->makeItem(['daily_rate' => 50, 'tax_rate' => 0]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-11-05', 'quantity' => 1],
        ]);
        $this->expectException(ValidationException::class);
        $this->service->transitionOrder($order, 'checked_in');
    }

    public function test_create_order_with_conflict_throws(): void
    {
        $item = $this->makeItem(['capacity' => 1]);
        $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-11-02', 'start_time' => '09:00', 'end_time' => '17:00', 'quantity' => 1],
        ]);

        $this->expectException(ValidationException::class);
        $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-11-02', 'start_time' => '10:00', 'end_time' => '11:00', 'quantity' => 1],
        ]);
    }

    public function test_create_order_increments_coupon_usage(): void
    {
        $item = $this->makeItem(['daily_rate' => 100, 'tax_rate' => 0]);
        $coupon = $this->makeCoupon(['code' => 'INC01']);
        $this->assertEquals(0, $coupon->used_count);
        $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-11-03', 'quantity' => 1],
        ], null, null, 'INC01');
        $this->assertEquals(1, $coupon->refresh()->used_count);
    }

    // --- Order Transitions ---

    public function test_transition_confirmed_to_checked_in(): void
    {
        $item = $this->makeItem(['daily_rate' => 50, 'tax_rate' => 0]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-12-01', 'quantity' => 1],
        ]);
        $order = $this->approve($order);
        $order = $this->service->transitionOrder($order, 'checked_in');
        $this->assertEquals('checked_in', $order->status);
        $this->assertNotNull($order->checked_in_at);
    }

    public function test_invalid_transition_throws(): void
    {
        $item = $this->makeItem(['daily_rate' => 50, 'tax_rate' => 0]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-12-02', 'quantity' => 1],
        ]);
        $order = $this->approve($order);
        $this->expectException(ValidationException::class);
        $this->service->transitionOrder($order, 'completed'); // confirmed → completed not allowed
    }

    public function test_full_lifecycle(): void
    {
        $item = $this->makeItem(['daily_rate' => 50, 'tax_rate' => 0]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2026-12-03', 'quantity' => 1],
        ]);
        $order = $this->approve($order);
        $order = $this->service->transitionOrder($order, 'checked_in');
        $order = $this->service->transitionOrder($order, 'checked_out');
        $order = $this->service->transitionOrder($order, 'completed');
        $this->assertEquals('completed', $order->status);
    }

    // --- Stale draft cleanup ---

    public function test_cleanup_stale_drafts_cancels_old_drafts_only(): void
    {
        $item = $this->makeItem();
        // Old draft (>= cutoff)
        $oldDraft = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2027-01-01', 'quantity' => 1],
        ]);
        \App\Domain\Models\Order::where('id', $oldDraft->id)
            ->update(['created_at' => now()->subHours(3)]);

        // Recent draft — must NOT be touched
        $recent = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $item->id, 'booking_date' => '2027-01-02', 'quantity' => 1],
        ]);

        $count = $this->service->cleanupStaleDrafts(60);
        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $oldDraft->fresh()->status);
        $this->assertSame('draft', $recent->fresh()->status);
    }

    // --- Consumable inventory: reserve / restore ---

    public function test_pending_transition_decrements_consumable_stock(): void
    {
        $consumable = $this->makeItem([
            'type' => 'consumable', 'unit_price' => 5, 'tax_rate' => 0, 'stock' => 10,
        ]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $consumable->id, 'booking_date' => '2027-02-01', 'quantity' => 3],
        ]);
        $this->service->transitionOrder($order, 'pending');
        $this->assertSame(7, (int) $consumable->fresh()->stock);
    }

    public function test_cancel_after_pending_restores_stock(): void
    {
        $consumable = $this->makeItem([
            'type' => 'consumable', 'unit_price' => 5, 'tax_rate' => 0, 'stock' => 5,
        ]);
        $order = $this->service->createOrder($this->user->id, [
            ['bookable_item_id' => $consumable->id, 'booking_date' => '2027-02-02', 'quantity' => 2],
        ]);
        $this->service->transitionOrder($order, 'pending');
        $this->assertSame(3, (int) $consumable->fresh()->stock);
        $this->service->transitionOrder($order, 'cancelled', 'reason');
        $this->assertSame(5, (int) $consumable->fresh()->stock);
    }
}
