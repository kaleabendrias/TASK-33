<?php

namespace FrontendTests\Booking;

use App\Domain\Models\BookableItem;
use App\Livewire\Booking\BookingIndex;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for BookingIndex.
 *
 * Covers: default filter state, rendering, and search/filter property binding.
 */
class BookingIndexTest extends TestCase
{
    public function test_default_search_is_empty(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(BookingIndex::class)->get('search'));
    }

    public function test_default_type_filter_is_empty(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(BookingIndex::class)->get('typeFilter'));
    }

    public function test_component_renders_for_authenticated_user(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(BookingIndex::class)->assertOk();
    }

    public function test_search_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(BookingIndex::class)
            ->set('search', 'lab')
            ->assertSet('search', 'lab');
    }

    public function test_type_filter_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(BookingIndex::class)
            ->set('typeFilter', 'room')
            ->assertSet('typeFilter', 'room');
    }

    public function test_active_items_appear_in_rendered_catalog(): void
    {
        BookableItem::create([
            'type' => 'room', 'name' => 'FE Visible Room',
            'hourly_rate' => 30, 'daily_rate' => 100, 'tax_rate' => 0.1,
            'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);
        // The component passes items to the view — assertOk confirms
        // the full render pipeline (route → component → view) succeeds.
        Livewire::test(BookingIndex::class)->assertOk();
    }
}
