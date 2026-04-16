<?php

namespace FrontendTests\Booking;

use App\Domain\Models\BookableItem;
use App\Livewire\Booking\BookingCreate;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for BookingCreate.
 *
 * Covers: default state, wizard step navigation logic (pure component
 * transitions), line-item cart management, and totals reset on empty cart.
 * Does not test success/failure of API calls — those belong in
 * API_tests/Livewire/LivewireComponentTest.php.
 */
class BookingCreateTest extends TestCase
{
    public function test_default_step_is_one(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals(1, Livewire::test(BookingCreate::class)->get('step'));
    }

    public function test_default_line_items_is_empty_array(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEmpty(Livewire::test(BookingCreate::class)->get('lineItems'));
    }

    public function test_default_totals_subtotal_is_zero(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals(0, Livewire::test(BookingCreate::class)->get('totals')['subtotal']);
    }

    public function test_default_coupon_valid_is_false(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertFalse(Livewire::test(BookingCreate::class)->get('couponValid'));
    }

    public function test_booking_date_defaults_to_today(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals(date('Y-m-d'), Livewire::test(BookingCreate::class)->get('bookingDate'));
    }

    public function test_next_step_with_empty_cart_sets_error_and_stays_on_step_one(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)->call('nextStep');
        $this->assertEquals(1, $component->get('step'));
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_prev_step_from_step_one_does_not_go_below_one(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)->call('prevStep');
        $this->assertEquals(1, $component->get('step'));
    }

    public function test_next_step_with_items_advances_to_step_two(): void
    {
        $item = BookableItem::create([
            'type' => 'room', 'name' => 'FE-Room', 'hourly_rate' => 30,
            'daily_rate' => 100, 'tax_rate' => 0.1, 'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);

        $component = Livewire::test(BookingCreate::class)
            ->set('lineItems', [[
                'bookable_item_id' => $item->id, 'booking_date' => '2026-12-01',
                'start_time' => null, 'end_time' => null, 'quantity' => 1,
            ]])
            ->call('nextStep');

        $this->assertEquals(2, $component->get('step'));
        $this->assertEquals('', $component->get('error'));
    }

    public function test_prev_step_from_step_two_returns_to_step_one(): void
    {
        $item = BookableItem::create([
            'type' => 'room', 'name' => 'FE-Room2', 'hourly_rate' => 30,
            'daily_rate' => 100, 'tax_rate' => 0.1, 'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);

        $component = Livewire::test(BookingCreate::class)
            ->set('lineItems', [[
                'bookable_item_id' => $item->id, 'booking_date' => '2026-12-01',
                'start_time' => null, 'end_time' => null, 'quantity' => 1,
            ]])
            ->call('nextStep')
            ->call('prevStep');

        $this->assertEquals(1, $component->get('step'));
    }

    public function test_remove_line_item_reindexes_array(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);

        $component = Livewire::test(BookingCreate::class)
            ->set('lineItems', [
                ['bookable_item_id' => 1, 'booking_date' => '2026-12-01', 'start_time' => null, 'end_time' => null, 'quantity' => 1],
                ['bookable_item_id' => 2, 'booking_date' => '2026-12-02', 'start_time' => null, 'end_time' => null, 'quantity' => 1],
            ])
            ->call('removeLineItem', 0);

        $items = $component->get('lineItems');
        $this->assertCount(1, $items);
        // Array must be re-indexed (keys start at 0 again).
        $this->assertArrayHasKey(0, $items);
        $this->assertEquals(2, $items[0]['bookable_item_id']);
    }

    public function test_recalculate_with_empty_cart_resets_totals(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)->call('recalculate');
        $this->assertEquals(0, $component->get('totals')['subtotal']);
        $this->assertEquals(0, $component->get('totals')['total']);
    }

    public function test_component_renders(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(BookingCreate::class)->assertOk();
    }
}
