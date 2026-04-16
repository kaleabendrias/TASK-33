<?php

namespace FrontendTests\Settlement;

use App\Livewire\Settlement\CommissionReport;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for CommissionReport.
 *
 * Covers: default date-range properties, rendering for authorized
 * roles, and date-range property binding.
 * Row-level isolation belongs in
 * API_tests/Livewire/LivewireAuthorizationTest.php.
 */
class CommissionReportTest extends TestCase
{
    public function test_default_date_from_is_start_of_current_month(): void
    {
        $gl = $this->createUser('group-leader');
        $this->actAs($gl);
        $this->assertEquals(
            now()->startOfMonth()->toDateString(),
            Livewire::test(CommissionReport::class)->get('dateFrom')
        );
    }

    public function test_default_date_to_is_today(): void
    {
        $gl = $this->createUser('group-leader');
        $this->actAs($gl);
        $this->assertEquals(
            now()->toDateString(),
            Livewire::test(CommissionReport::class)->get('dateTo')
        );
    }

    public function test_component_renders_for_group_leader(): void
    {
        $gl = $this->createUser('group-leader');
        $this->actAs($gl);
        Livewire::test(CommissionReport::class)->assertOk();
    }

    public function test_component_renders_for_admin(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(CommissionReport::class)->assertOk();
    }

    public function test_date_range_property_binding(): void
    {
        $gl = $this->createUser('group-leader');
        $this->actAs($gl);
        Livewire::test(CommissionReport::class)
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->assertSet('dateFrom', '2026-01-01')
            ->assertSet('dateTo', '2026-01-31');
    }
}
