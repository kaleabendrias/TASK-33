<?php

namespace ApiTests\Pricing;

use App\Domain\Models\BookableItem;
use App\Domain\Models\PricingRule;
use ApiTests\TestCase;

class PricingRuleApiTest extends TestCase
{
    private function makeItem(): BookableItem
    {
        return BookableItem::create([
            'type' => 'room', 'name' => 'Pr ' . mt_rand(),
            'hourly_rate' => 50, 'daily_rate' => 200, 'tax_rate' => 0.0,
            'capacity' => 5, 'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Gold tier rule',
            'member_tier' => 'gold',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'discount_pct',
            'adjustment_value' => 10,
            'priority' => 100,
            'is_active' => true,
        ], $overrides);
    }

    public function test_admin_can_create_pricing_rule(): void
    {
        $admin = $this->createUser('admin');
        $resp = $this->postJson('/api/admin/pricing-rules', $this->payload(), $this->authHeaders($admin));
        $resp->assertStatus(201)
            ->assertJsonPath('data.member_tier', 'gold')
            ->assertJsonPath('data.adjustment_type', 'discount_pct');
    }

    public function test_non_admin_cannot_create_pricing_rule(): void
    {
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/admin/pricing-rules', $this->payload(), $this->authHeaders($staff))
            ->assertStatus(403);
    }

    public function test_unauthenticated_cannot_create(): void
    {
        $this->postJson('/api/admin/pricing-rules', $this->payload())->assertStatus(401);
    }

    public function test_admin_can_list_filter_show(): void
    {
        $admin = $this->createUser('admin');
        PricingRule::create(['name' => 'A', 'member_tier' => 'silver', 'effective_from' => '2026-01-01', 'adjustment_type' => 'multiplier', 'adjustment_value' => 1.1, 'priority' => 100]);
        PricingRule::create(['name' => 'B', 'member_tier' => 'gold', 'effective_from' => '2026-01-01', 'adjustment_type' => 'multiplier', 'adjustment_value' => 0.9, 'priority' => 100]);

        $resp = $this->getJson('/api/admin/pricing-rules?member_tier=gold', $this->authHeaders($admin));
        $resp->assertOk();
        $rows = $resp->json('data');
        foreach ($rows as $r) {
            $this->assertEquals('gold', $r['member_tier']);
        }

        $first = PricingRule::first();
        $this->getJson("/api/admin/pricing-rules/{$first->id}", $this->authHeaders($admin))
            ->assertOk()->assertJsonPath('data.id', $first->id);
    }

    public function test_admin_can_update_and_delete_rule(): void
    {
        $admin = $this->createUser('admin');
        $rule = PricingRule::create(['name' => 'Init', 'effective_from' => '2026-01-01', 'adjustment_type' => 'multiplier', 'adjustment_value' => 1, 'priority' => 100]);

        $this->putJson("/api/admin/pricing-rules/{$rule->id}", ['name' => 'Renamed', 'priority' => 50], $this->authHeaders($admin))
            ->assertOk()->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.priority', 50);

        $this->deleteJson("/api/admin/pricing-rules/{$rule->id}", [], $this->authHeaders($admin))
            ->assertOk();

        $this->assertNull(PricingRule::find($rule->id));
    }

    public function test_validation_rejects_bad_inputs(): void
    {
        $admin = $this->createUser('admin');
        // missing name + invalid adjustment_type
        $this->postJson('/api/admin/pricing-rules', [
            'name' => '', 'effective_from' => '2026-01-01',
            'adjustment_type' => 'gibberish', 'adjustment_value' => 1,
        ], $this->authHeaders($admin))->assertStatus(422);

        // discount_pct > 100
        $this->postJson('/api/admin/pricing-rules', $this->payload([
            'adjustment_type' => 'discount_pct', 'adjustment_value' => 150,
        ]), $this->authHeaders($admin))->assertStatus(422);

        // effective_until before effective_from
        $this->postJson('/api/admin/pricing-rules', $this->payload([
            'effective_from' => '2026-12-01', 'effective_until' => '2026-01-01',
        ]), $this->authHeaders($admin))->assertStatus(422);

        // bad days_of_week
        $this->postJson('/api/admin/pricing-rules', $this->payload([
            'days_of_week' => '8,9',
        ]), $this->authHeaders($admin))->assertStatus(422);

        // time_slot_end <= time_slot_start
        $this->postJson('/api/admin/pricing-rules', $this->payload([
            'time_slot_start' => '18:00', 'time_slot_end' => '17:00',
        ]), $this->authHeaders($admin))->assertStatus(422);

        // min > max headcount
        $this->postJson('/api/admin/pricing-rules', $this->payload([
            'min_headcount' => 10, 'max_headcount' => 5,
        ]), $this->authHeaders($admin))->assertStatus(422);
    }

    public function test_create_rule_with_all_dimensions(): void
    {
        $admin = $this->createUser('admin');
        $item = $this->makeItem();
        $resp = $this->postJson('/api/admin/pricing-rules', [
            'name' => 'Peak weekend gold school',
            'bookable_item_id' => $item->id,
            'time_slot_start' => '17:00',
            'time_slot_end' => '21:00',
            'days_of_week' => '6,7',
            'min_headcount' => 5,
            'max_headcount' => 50,
            'member_tier' => 'gold',
            'package_code' => 'SCHOOL',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
            'adjustment_type' => 'multiplier',
            'adjustment_value' => 1.5,
            'priority' => 10,
            'is_active' => true,
        ], $this->authHeaders($admin));
        $resp->assertStatus(201)
            ->assertJsonPath('data.bookable_item_id', $item->id)
            ->assertJsonPath('data.package_code', 'SCHOOL');
    }
}
