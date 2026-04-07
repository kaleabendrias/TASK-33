<?php

namespace ApiTests\Settlement;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use App\Application\Services\BookingService;
use App\Application\Services\SettlementService;
use ApiTests\TestCase;

class SettlementApiTest extends TestCase
{
    private function makeItem(): BookableItem
    {
        return BookableItem::create([
            'type' => 'room', 'name' => 'Stl ' . mt_rand(),
            'hourly_rate' => 50, 'daily_rate' => 100, 'tax_rate' => 0.0,
            'capacity' => 5, 'is_active' => true,
        ]);
    }

    public function test_admin_can_generate_settlement(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/admin/settlements/generate', [
            'period_start' => '2026-01-01',
            'period_end'   => '2026-12-31',
            'cycle_type'   => 'weekly',
        ], $this->authHeaders($admin))
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['reference', 'cycle_type'], 'discrepancies']);
    }

    public function test_non_admin_cannot_generate_settlement(): void
    {
        $gl = $this->createUser('group-leader');
        $this->postJson('/api/admin/settlements/generate', [
            'period_start' => '2026-01-01',
            'period_end'   => '2026-12-31',
            'cycle_type'   => 'weekly',
        ], $this->authHeaders($gl))->assertStatus(403);
    }

    public function test_admin_can_finalize(): void
    {
        $admin = $this->createUser('admin');
        $stl = Settlement::create([
            'reference' => 'STL-T1',
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 500, 'refund_total' => 0, 'net_amount' => 500,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $this->postJson("/api/admin/settlements/{$stl->id}/finalize", [], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized');
    }

    public function test_settlement_index_admin_sees_all(): void
    {
        $admin = $this->createUser('admin');
        Settlement::create([
            'reference' => 'STL-A', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        $resp = $this->getJson('/api/settlements', $this->authHeaders($admin));
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, count($resp->json('data')));
    }

    public function test_settlement_index_group_leader_isolation(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $other = $this->createStaffWithProfile('group-leader');

        $stl = Settlement::create([
            'reference' => 'STL-ISO', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        // Commission belongs to "other"
        Commission::create([
            'group_leader_id' => $other->id, 'settlement_id' => $stl->id,
            'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-31', 'cycle_type' => 'weekly',
            'attributed_revenue' => 100, 'commission_rate' => 0.1, 'commission_amount' => 10,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-02-05',
        ]);

        $resp = $this->getJson('/api/settlements', $this->authHeaders($gl));
        $resp->assertOk();
        $refs = collect($resp->json('data'))->pluck('reference');
        $this->assertFalse($refs->contains('STL-ISO'), 'GL should not see other GL\'s settlement');
    }

    public function test_commissions_endpoint_filters(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $resp = $this->getJson('/api/commissions?from=2026-01-01&to=2026-12-31', $this->authHeaders($gl));
        $resp->assertOk()->assertJsonStructure(['data', 'total']);
    }
}
