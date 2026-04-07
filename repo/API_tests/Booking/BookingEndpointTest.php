<?php

namespace ApiTests\Booking;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Coupon;
use ApiTests\TestCase;

class BookingEndpointTest extends TestCase
{
    private BookableItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->item = BookableItem::create([
            'type' => 'room', 'name' => 'Endpoint Room', 'hourly_rate' => 30,
            'daily_rate' => 150, 'tax_rate' => 0.0800, 'capacity' => 2, 'is_active' => true,
        ]);
        Coupon::create([
            'code' => 'APITEST10', 'discount_type' => 'percentage', 'discount_value' => 10,
            'min_order_amount' => 0, 'valid_from' => now()->subDay(), 'is_active' => true,
        ]);
    }

    public function test_list_bookable_items(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/bookings/items', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_check_availability(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/bookings/check-availability', [
            'bookable_item_id' => $this->item->id,
            'booking_date' => '2026-09-01',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], $this->authHeaders($user))->assertOk()->assertJsonPath('available', true);
    }

    public function test_calculate_totals(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/bookings/calculate-totals', [
            'line_items' => [
                ['bookable_item_id' => $this->item->id, 'booking_date' => '2026-09-01', 'start_time' => '09:00', 'end_time' => '12:00', 'quantity' => 1],
            ],
        ], $this->authHeaders($user))->assertOk()->assertJsonStructure(['subtotal', 'tax_amount', 'total', 'lines']);
    }

    public function test_validate_coupon(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/bookings/validate-coupon', [
            'code' => 'APITEST10',
            'subtotal' => 100.00,
        ], $this->authHeaders($user))->assertOk()->assertJsonPath('valid', true);
    }

    public function test_validate_invalid_coupon(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/bookings/validate-coupon', [
            'code' => 'NONEXISTENT',
            'subtotal' => 100.00,
        ], $this->authHeaders($user))->assertOk()->assertJsonPath('valid', false);
    }

    public function test_calculate_totals_with_coupon(): void
    {
        $user = $this->createUser('user');
        $resp = $this->postJson('/api/bookings/calculate-totals', [
            'line_items' => [
                ['bookable_item_id' => $this->item->id, 'booking_date' => '2026-09-01', 'quantity' => 1],
            ],
            'coupon_code' => 'APITEST10',
        ], $this->authHeaders($user));
        $resp->assertOk();
        $this->assertGreaterThan(0, $resp->json('discount'));
    }
}
