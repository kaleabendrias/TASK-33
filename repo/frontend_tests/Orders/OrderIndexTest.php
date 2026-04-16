<?php

namespace FrontendTests\Orders;

use App\Livewire\Orders\OrderIndex;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for OrderIndex.
 *
 * Covers: default filter state, rendering, and property binding.
 * Row-level isolation and pagination correctness belong in
 * API_tests/Livewire/LivewireAuthorizationTest.php.
 */
class OrderIndexTest extends TestCase
{
    public function test_default_search_is_empty(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(OrderIndex::class)->get('search'));
    }

    public function test_default_status_filter_is_empty(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals('', Livewire::test(OrderIndex::class)->get('statusFilter'));
    }

    public function test_component_renders_for_authenticated_user(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(OrderIndex::class)->assertOk();
    }

    public function test_search_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(OrderIndex::class)
            ->set('search', 'ORD-2026')
            ->assertSet('search', 'ORD-2026');
    }

    public function test_status_filter_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(OrderIndex::class)
            ->set('statusFilter', 'confirmed')
            ->assertSet('statusFilter', 'confirmed');
    }
}
