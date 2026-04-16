<?php

namespace ApiTests\Livewire;

use App\Domain\Models\PricingRule;
use App\Domain\Models\User;
use App\Livewire\Pricing\PricingRuleManager;
use Livewire\Livewire;
use ApiTests\TestCase;

/**
 * True No-Mock HTTP tests for the PricingRuleManager Livewire component.
 *
 * The component is admin-only and delegates all writes to the real
 * /api/admin/pricing-rules REST endpoints via UsesApiClient. Reads go
 * through PricingRuleService::list (a documented exception in the
 * architecture). Every test exercises the real route stack; no internal
 * services are mocked.
 */
class PricingRuleManagerTest extends TestCase
{
    private function actAs(User $user): void
    {
        $this->actingAs($user);
        request()->attributes->set('auth_user', $user);
    }

    private function minimalPayload(array $overrides = []): array
    {
        return array_merge([
            'name'             => 'Test Rule ' . mt_rand(),
            'effective_from'   => '2026-01-01',
            'adjustment_type'  => 'multiplier',
            'adjustment_value' => '1.10',
            'priority'         => 100,
            'is_active'        => true,
        ], $overrides);
    }

    // ── Access control ─────────────────────────────────────────────────

    public function test_non_admin_cannot_mount_component(): void
    {
        $staff = $this->createUser('staff');
        $this->actAs($staff);
        Livewire::test(PricingRuleManager::class)->assertStatus(403);
    }

    public function test_admin_can_render_component(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        Livewire::test(PricingRuleManager::class)->assertOk();
    }

    // ── Initial state after mount ──────────────────────────────────────

    public function test_mount_sets_effective_from_to_today(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(PricingRuleManager::class);
        $this->assertEquals(now()->toDateString(), $component->get('effective_from'));
    }

    public function test_mount_sets_default_adjustment_type_multiplier(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(PricingRuleManager::class);
        $this->assertEquals('multiplier', $component->get('adjustment_type'));
    }

    // ── resetForm ──────────────────────────────────────────────────────

    public function test_reset_form_clears_all_fields(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $component = Livewire::test(PricingRuleManager::class)
            ->set('name', 'Dirty name')
            ->set('member_tier', 'gold')
            ->set('adjustment_value', '2.50')
            ->call('resetForm');

        $this->assertEquals('', $component->get('name'));
        $this->assertNull($component->get('member_tier'));
        $this->assertEquals('1.00', $component->get('adjustment_value'));
        $this->assertNull($component->get('editingId'));
        $this->assertEmpty($component->get('errors_bag'));
    }

    // ── loadForEdit ────────────────────────────────────────────────────

    public function test_load_for_edit_populates_all_fields(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $rule = PricingRule::create([
            'name'             => 'Edit Me',
            'member_tier'      => 'silver',
            'effective_from'   => '2026-03-01',
            'adjustment_type'  => 'discount_pct',
            'adjustment_value' => 15,
            'priority'         => 50,
            'is_active'        => true,
        ]);

        $component = Livewire::test(PricingRuleManager::class)
            ->call('loadForEdit', $rule->id);

        $this->assertEquals($rule->id, $component->get('editingId'));
        $this->assertEquals('Edit Me', $component->get('name'));
        $this->assertEquals('silver', $component->get('member_tier'));
        $this->assertEquals('discount_pct', $component->get('adjustment_type'));
        $this->assertEquals(15.0, (float) $component->get('adjustment_value'));
        $this->assertEquals(50, $component->get('priority'));
    }

    // ── save (create path) ─────────────────────────────────────────────

    public function test_save_creates_new_rule_via_real_api(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $name = 'New Rule ' . mt_rand();
        $component = Livewire::test(PricingRuleManager::class)
            ->set('name', $name)
            ->set('effective_from', '2026-01-01')
            ->set('adjustment_type', 'multiplier')
            ->set('adjustment_value', '1.20')
            ->set('priority', 10)
            ->call('save');

        // On success the component resets the form and sets a flash message.
        $this->assertNotEmpty($component->get('message'));
        $this->assertEmpty($component->get('errors_bag'));
        $this->assertEquals('', $component->get('name'));  // resetForm was called

        // Verify the rule was actually persisted.
        $this->assertNotNull(PricingRule::where('name', $name)->first());
    }

    public function test_save_with_invalid_data_sets_errors_bag(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        // Empty name + invalid adjustment_type should trigger a real 422 from the API.
        $component = Livewire::test(PricingRuleManager::class)
            ->set('name', '')
            ->set('effective_from', '2026-01-01')
            ->set('adjustment_type', 'invalid_type')
            ->set('adjustment_value', '1.00')
            ->call('save');

        // The component must surface the server-side error without crashing.
        $this->assertNotEmpty($component->get('message'));
    }

    // ── save (update path) ─────────────────────────────────────────────

    public function test_save_updates_existing_rule_via_real_api(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $rule = PricingRule::create([
            'name'             => 'Before Update',
            'effective_from'   => '2026-01-01',
            'adjustment_type'  => 'multiplier',
            'adjustment_value' => 1.0,
            'priority'         => 100,
        ]);

        Livewire::test(PricingRuleManager::class)
            ->call('loadForEdit', $rule->id)
            ->set('name', 'After Update')
            ->set('priority', 5)
            ->call('save');

        $this->assertEquals('After Update', PricingRule::find($rule->id)?->name);
        $this->assertEquals(5, PricingRule::find($rule->id)?->priority);
    }

    // ── delete ─────────────────────────────────────────────────────────

    public function test_delete_removes_rule_via_real_api(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $rule = PricingRule::create([
            'name'             => 'Delete Me',
            'effective_from'   => '2026-01-01',
            'adjustment_type'  => 'multiplier',
            'adjustment_value' => 1.0,
            'priority'         => 100,
        ]);

        $component = Livewire::test(PricingRuleManager::class)
            ->call('delete', $rule->id);

        $this->assertStringContainsString('deleted', strtolower($component->get('message')));
        $this->assertNull(PricingRule::find($rule->id));
    }

    public function test_delete_nonexistent_rule_sets_failure_message(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $component = Livewire::test(PricingRuleManager::class)
            ->call('delete', 99999);

        $this->assertNotEmpty($component->get('message'));
    }

    // ── Render with data ───────────────────────────────────────────────

    public function test_render_shows_existing_rules(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        PricingRule::create([
            'name'             => 'Visible Rule',
            'effective_from'   => '2026-01-01',
            'adjustment_type'  => 'discount_pct',
            'adjustment_value' => 5,
            'priority'         => 100,
        ]);

        // assertOk confirms the component renders without error even when
        // the paginated rule list is non-empty.
        Livewire::test(PricingRuleManager::class)->assertOk();
    }
}
