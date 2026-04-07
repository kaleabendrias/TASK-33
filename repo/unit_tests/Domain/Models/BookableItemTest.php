<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use App\Domain\Models\OrderLineItem;
use App\Domain\Models\User;
use UnitTests\TestCase;

class BookableItemTest extends TestCase
{
    private function makeItem(array $overrides = []): BookableItem
    {
        return BookableItem::create(array_merge([
            'type' => 'room', 'name' => 'Test Room', 'hourly_rate' => 50, 'daily_rate' => 300,
            'tax_rate' => 0.08, 'capacity' => 2, 'is_active' => true,
        ], $overrides));
    }

    public function test_is_consumable(): void
    {
        $consumable = $this->makeItem(['type' => 'consumable', 'unit_price' => 10, 'stock' => 50]);
        $room = $this->makeItem(['type' => 'room']);
        $this->assertTrue($consumable->isConsumable());
        $this->assertFalse($room->isConsumable());
    }

    public function test_has_availability_empty_schedule(): void
    {
        $item = $this->makeItem(['capacity' => 1]);
        $this->assertTrue($item->hasAvailability('2026-05-01', '09:00', '12:00'));
    }

    public function test_has_availability_conflict(): void
    {
        $item = $this->makeItem(['capacity' => 1]);
        $user = User::create(['username' => 'av_user', 'password' => 'TestPass@12345!', 'full_name' => 'T', 'role' => 'user']);
        $order = Order::create([
            'order_number' => 'ORD-TEST-0001', 'user_id' => $user->id, 'status' => 'confirmed',
            'subtotal' => 100, 'tax_amount' => 8, 'total' => 108,
        ]);
        OrderLineItem::create([
            'order_id' => $order->id, 'bookable_item_id' => $item->id,
            'booking_date' => '2026-05-01', 'start_time' => '09:00', 'end_time' => '12:00',
            'quantity' => 1, 'unit_price' => 100, 'line_subtotal' => 100, 'line_tax' => 8, 'line_total' => 108,
        ]);

        $this->assertFalse($item->hasAvailability('2026-05-01', '10:00', '11:00'));
        $this->assertTrue($item->hasAvailability('2026-05-01', '13:00', '15:00'));
        $this->assertTrue($item->hasAvailability('2026-05-02', '09:00', '12:00'));
    }

    public function test_capacity_allows_multiple_bookings(): void
    {
        $item = $this->makeItem(['capacity' => 3]);
        $user = User::create(['username' => 'cap_user', 'password' => 'TestPass@12345!', 'full_name' => 'T', 'role' => 'user']);

        for ($i = 0; $i < 2; $i++) {
            $order = Order::create(['order_number' => "ORD-CAP-{$i}", 'user_id' => $user->id, 'status' => 'confirmed', 'subtotal' => 50, 'total' => 50]);
            OrderLineItem::create([
                'order_id' => $order->id, 'bookable_item_id' => $item->id,
                'booking_date' => '2026-06-01', 'start_time' => '09:00', 'end_time' => '12:00',
                'quantity' => 1, 'unit_price' => 50, 'line_subtotal' => 50, 'line_tax' => 0, 'line_total' => 50,
            ]);
        }

        $this->assertTrue($item->hasAvailability('2026-06-01', '09:00', '12:00', 1));
        $this->assertFalse($item->hasAvailability('2026-06-01', '09:00', '12:00', 2));
    }

    public function test_consumable_stock_check(): void
    {
        $item = $this->makeItem(['type' => 'consumable', 'unit_price' => 10, 'stock' => 5]);
        $this->assertTrue($item->hasAvailability('2026-05-01', null, null, 5));
        $this->assertFalse($item->hasAvailability('2026-05-01', null, null, 6));
    }

    public function test_consumable_null_stock_unlimited(): void
    {
        $item = $this->makeItem(['type' => 'consumable', 'unit_price' => 10, 'stock' => null]);
        $this->assertTrue($item->hasAvailability('2026-05-01', null, null, 9999));
    }

    public function test_cancelled_orders_dont_block(): void
    {
        $item = $this->makeItem(['capacity' => 1]);
        $user = User::create(['username' => 'canc_user', 'password' => 'TestPass@12345!', 'full_name' => 'T', 'role' => 'user']);
        $order = Order::create(['order_number' => 'ORD-CANC-01', 'user_id' => $user->id, 'status' => 'cancelled', 'subtotal' => 50, 'total' => 50]);
        OrderLineItem::create([
            'order_id' => $order->id, 'bookable_item_id' => $item->id,
            'booking_date' => '2026-07-01', 'start_time' => '09:00', 'end_time' => '17:00',
            'quantity' => 1, 'unit_price' => 50, 'line_subtotal' => 50, 'line_tax' => 0, 'line_total' => 50,
        ]);
        $this->assertTrue($item->hasAvailability('2026-07-01', '09:00', '17:00'));
    }
}
