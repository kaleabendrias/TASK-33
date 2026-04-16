<?php

namespace FrontendTests\Dashboard;

use App\Livewire\Dashboard\DashboardPage;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for DashboardPage.
 *
 * Covers: default date-range properties, rendering for each role,
 * and date-range updates. Does not assert on specific metric values
 * (those belong in API_tests/Dashboard/).
 */
class DashboardPageTest extends TestCase
{
    public function test_default_date_from_is_start_of_current_month(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(DashboardPage::class);
        $this->assertEquals(now()->startOfMonth()->toDateString(), $component->get('dateFrom'));
    }

    public function test_default_date_to_is_today(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(DashboardPage::class);
        $this->assertEquals(now()->toDateString(), $component->get('dateTo'));
    }

    public function test_renders_for_admin(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    public function test_renders_for_staff(): void
    {
        $staff = $this->createUser('staff');
        $this->actAs($staff);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    public function test_renders_for_group_leader(): void
    {
        $gl = $this->createUser('group-leader');
        $this->actAs($gl);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    public function test_renders_for_viewer(): void
    {
        $viewer = $this->createUser('user');
        $this->actAs($viewer);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    public function test_date_range_can_be_updated(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(DashboardPage::class)
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->assertSet('dateFrom', '2026-01-01')
            ->assertSet('dateTo', '2026-01-31');
    }
}
