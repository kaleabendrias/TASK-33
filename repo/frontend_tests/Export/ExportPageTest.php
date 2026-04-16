<?php

namespace FrontendTests\Export;

use App\Livewire\Export\ExportPage;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for ExportPage.
 *
 * Covers: default property state, mount date initialization,
 * rendering, and property binding.
 * Download success/failure paths belong in
 * API_tests/Livewire/LivewireComponentTest.php.
 */
class ExportPageTest extends TestCase
{
    public function test_default_export_type_is_orders(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals('orders', Livewire::test(ExportPage::class)->get('exportType'));
    }

    public function test_default_format_is_csv(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals('csv', Livewire::test(ExportPage::class)->get('format'));
    }

    public function test_default_date_from_is_start_of_current_month(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals(
            now()->startOfMonth()->toDateString(),
            Livewire::test(ExportPage::class)->get('dateFrom')
        );
    }

    public function test_default_date_to_is_today(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $this->assertEquals(
            now()->toDateString(),
            Livewire::test(ExportPage::class)->get('dateTo')
        );
    }

    public function test_component_renders_for_authenticated_user(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(ExportPage::class)->assertOk();
    }

    public function test_export_type_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(ExportPage::class)
            ->set('exportType', 'bookings')
            ->assertSet('exportType', 'bookings');
    }

    public function test_format_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(ExportPage::class)
            ->set('format', 'pdf')
            ->assertSet('format', 'pdf');
    }

    public function test_date_range_property_binding(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(ExportPage::class)
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->assertSet('dateFrom', '2026-01-01')
            ->assertSet('dateTo', '2026-01-31');
    }
}
