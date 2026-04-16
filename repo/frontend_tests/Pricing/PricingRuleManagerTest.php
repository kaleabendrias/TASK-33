<?php

namespace FrontendTests\Pricing;

use App\Livewire\Pricing\PricingRuleManager;
use Livewire\Livewire;
use FrontendTests\TestCase;

/**
 * Frontend unit tests for PricingRuleManager.
 *
 * Covers: admin-only gate, default form state, effective_from
 * initialization, resetForm() clearing all fields, and property binding.
 * Save/delete API calls and full CRUD flows belong in
 * API_tests/Livewire/PricingRuleManagerTest.php.
 */
class PricingRuleManagerTest extends TestCase
{
    public function test_non_admin_is_blocked(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(PricingRuleManager::class)->assertStatus(403);
    }

    public function test_staff_is_blocked(): void
    {
        $staff = $this->createUser('staff');
        $this->actAs($staff);
        Livewire::test(PricingRuleManager::class)->assertStatus(403);
    }

    public function test_component_renders_for_admin(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(PricingRuleManager::class)->assertOk();
    }

    public function test_default_editing_id_is_null(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertNull(Livewire::test(PricingRuleManager::class)->get('editingId'));
    }

    public function test_default_name_is_empty(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals('', Livewire::test(PricingRuleManager::class)->get('name'));
    }

    public function test_default_adjustment_type_is_multiplier(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals('multiplier', Livewire::test(PricingRuleManager::class)->get('adjustment_type'));
    }

    public function test_default_adjustment_value_is_one(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals('1.00', Livewire::test(PricingRuleManager::class)->get('adjustment_value'));
    }

    public function test_default_priority_is_100(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals(100, Livewire::test(PricingRuleManager::class)->get('priority'));
    }

    public function test_default_is_active_is_true(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertTrue(Livewire::test(PricingRuleManager::class)->get('is_active'));
    }

    public function test_default_message_is_empty(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals('', Livewire::test(PricingRuleManager::class)->get('message'));
    }

    public function test_default_errors_bag_is_empty(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEmpty(Livewire::test(PricingRuleManager::class)->get('errors_bag'));
    }

    public function test_effective_from_defaults_to_today(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $this->assertEquals(
            now()->toDateString(),
            Livewire::test(PricingRuleManager::class)->get('effective_from')
        );
    }

    public function test_reset_form_clears_name_and_editing_id(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(PricingRuleManager::class)
            ->set('name', 'Weekend Rate')
            ->set('adjustment_value', '1.50')
            ->call('resetForm');
        $this->assertEquals('', $component->get('name'));
        $this->assertNull($component->get('editingId'));
        $this->assertEquals('1.00', $component->get('adjustment_value'));
    }

    public function test_name_property_binding(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(PricingRuleManager::class)
            ->set('name', 'Peak Hours')
            ->assertSet('name', 'Peak Hours');
    }
}
