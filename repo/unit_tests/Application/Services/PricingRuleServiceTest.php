<?php

namespace UnitTests\Application\Services;

use App\Application\Services\PricingRuleService;
use App\Domain\Models\PricingRule;
use Illuminate\Validation\ValidationException;
use UnitTests\TestCase;

class PricingRuleServiceTest extends TestCase
{
    private PricingRuleService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(PricingRuleService::class);
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rule',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'multiplier',
            'adjustment_value' => 1.0,
            'priority' => 100,
            'is_active' => true,
        ], $overrides);
    }

    public function test_create_persists_rule(): void
    {
        $r = $this->svc->create($this->valid(['name' => 'A', 'member_tier' => 'gold']));
        $this->assertNotNull($r->id);
        $this->assertEquals('gold', $r->member_tier);
    }

    public function test_list_filters_by_tier(): void
    {
        $this->svc->create($this->valid(['name' => 'X', 'member_tier' => 'silver']));
        $this->svc->create($this->valid(['name' => 'Y', 'member_tier' => 'gold']));
        $rows = $this->svc->list(['member_tier' => 'gold']);
        $this->assertEquals(1, $rows->count());
        $this->assertEquals('gold', $rows->first()->member_tier);
    }

    public function test_list_filters_by_active(): void
    {
        $this->svc->create($this->valid(['name' => 'on']));
        $this->svc->create($this->valid(['name' => 'off', 'is_active' => false]));
        $rows = $this->svc->list(['is_active' => true]);
        foreach ($rows as $r) $this->assertTrue($r->is_active);
    }

    public function test_update_changes_fields(): void
    {
        $r = $this->svc->create($this->valid(['name' => 'Initial', 'priority' => 200]));
        $u = $this->svc->update($r->id, ['name' => 'Renamed', 'priority' => 10]);
        $this->assertEquals('Renamed', $u->name);
        $this->assertEquals(10, $u->priority);
    }

    public function test_delete_removes_rule(): void
    {
        $r = $this->svc->create($this->valid(['name' => 'Doomed']));
        $this->svc->delete($r->id);
        $this->assertNull(PricingRule::find($r->id));
    }

    public function test_validation_requires_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid(['name' => '']));
    }

    public function test_validation_rejects_bad_adjustment_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid(['adjustment_type' => 'gibberish']));
    }

    public function test_validation_rejects_negative_adjustment_value(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid(['adjustment_value' => -1]));
    }

    public function test_validation_rejects_pct_above_100(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid([
            'adjustment_type' => 'discount_pct', 'adjustment_value' => 200,
        ]));
    }

    public function test_validation_rejects_bad_dates(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid([
            'effective_from' => '2026-12-01', 'effective_until' => '2026-01-01',
        ]));
    }

    public function test_validation_rejects_bad_member_tier(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid(['member_tier' => 'mega']));
    }

    public function test_validation_rejects_bad_days_of_week(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid(['days_of_week' => '0,8']));
    }

    public function test_validation_rejects_inverted_time_slot(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid([
            'time_slot_start' => '21:00', 'time_slot_end' => '17:00',
        ]));
    }

    public function test_validation_rejects_inverted_headcount(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid([
            'min_headcount' => 50, 'max_headcount' => 5,
        ]));
    }

    public function test_validation_rejects_negative_priority(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create($this->valid(['priority' => -5]));
    }

    public function test_validation_rejects_missing_effective_from(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->create([
            'name' => 'X', 'adjustment_type' => 'multiplier', 'adjustment_value' => 1,
        ]);
    }

    public function test_partial_update_does_not_revalidate_unset_fields(): void
    {
        $r = $this->svc->create($this->valid(['name' => 'P']));
        // Only updating priority — name remains valid (not revalidated)
        $u = $this->svc->update($r->id, ['priority' => 5]);
        $this->assertEquals(5, $u->priority);
    }
}
