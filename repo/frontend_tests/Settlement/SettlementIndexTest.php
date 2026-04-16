<?php

namespace FrontendTests\Settlement;

use App\Livewire\Settlement\SettlementIndex;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for SettlementIndex.
 *
 * Covers: default date-range properties, cycleType default, rendering
 * for authorized roles, and property binding.
 * Row-level isolation (gl1 cannot see gl2's settlements) belongs in
 * API_tests/Livewire/LivewireAuthorizationTest.php.
 */
class SettlementIndexTest extends TestCase
{
    public function test_default_period_start_is_start_of_current_month(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals(
            now()->startOfMonth()->toDateString(),
            Livewire::test(SettlementIndex::class)->get('periodStart')
        );
    }

    public function test_default_period_end_is_today(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals(
            now()->toDateString(),
            Livewire::test(SettlementIndex::class)->get('periodEnd')
        );
    }

    public function test_default_cycle_type_is_weekly(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals('weekly', Livewire::test(SettlementIndex::class)->get('cycleType'));
    }

    public function test_default_message_is_empty(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals('', Livewire::test(SettlementIndex::class)->get('message'));
    }

    public function test_component_renders_for_admin(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(SettlementIndex::class)->assertOk();
    }

    public function test_component_renders_for_staff(): void
    {
        $staff = $this->createUser('staff');
        $this->actAs($staff);
        Livewire::test(SettlementIndex::class)->assertOk();
    }

    public function test_period_property_binding(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(SettlementIndex::class)
            ->set('periodStart', '2026-01-01')
            ->set('periodEnd', '2026-01-31')
            ->assertSet('periodStart', '2026-01-01')
            ->assertSet('periodEnd', '2026-01-31');
    }

    public function test_cycle_type_property_binding(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(SettlementIndex::class)
            ->set('cycleType', 'monthly')
            ->assertSet('cycleType', 'monthly');
    }
}
