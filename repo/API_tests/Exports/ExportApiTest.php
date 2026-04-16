<?php

namespace ApiTests\Exports;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use ApiTests\TestCase;

class ExportApiTest extends TestCase
{
    public function test_export_orders_csv(): void
    {
        $admin = $this->createUser('admin');
        Order::create([
            'order_number' => 'ORD-EX-1', 'user_id' => $admin->id,
            'status' => 'completed', 'subtotal' => 100, 'tax_amount' => 10,
            'discount_amount' => 0, 'total' => 110, 'confirmed_at' => now(),
        ]);
        $resp = $this->postJson('/api/exports', [
            'type' => 'orders', 'format' => 'csv',
            'date_from' => now()->subYear()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ], $this->authHeaders($admin));
        $resp->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $resp->headers->get('content-type'));
    }

    public function test_export_orders_pdf(): void
    {
        $admin = $this->createUser('admin');
        $resp = $this->postJson('/api/exports', [
            'type' => 'orders', 'format' => 'pdf',
            'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        ], $this->authHeaders($admin));
        $resp->assertOk();
        $this->assertStringContainsString('pdf', strtolower((string) $resp->headers->get('content-type')));
    }

    public function test_export_settlements_isolation_by_group_leader(): void
    {
        $gl = $this->createUser('group-leader');
        $other = $this->createUser('group-leader');
        $stl = Settlement::create([
            'reference' => 'STL-EX', 'period_start' => '2026-06-01', 'period_end' => '2026-06-30',
            'gross_amount' => 200, 'refund_total' => 0, 'net_amount' => 200,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        Commission::create([
            'group_leader_id' => $other->id, 'settlement_id' => $stl->id,
            'cycle_start' => '2026-06-01', 'cycle_end' => '2026-06-30', 'cycle_type' => 'weekly',
            'attributed_revenue' => 200, 'commission_rate' => 0.1, 'commission_amount' => 20,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-07-05',
        ]);

        $resp = $this->postJson('/api/exports', [
            'type' => 'settlements', 'format' => 'csv',
            'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ], $this->authHeaders($gl));
        $resp->assertOk();
        $this->assertStringNotContainsString('STL-EX', $resp->getContent());
    }

    public function test_export_commissions_csv(): void
    {
        $admin = $this->createUser('admin');
        $resp = $this->postJson('/api/exports', [
            'type' => 'commissions', 'format' => 'csv',
            'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        ], $this->authHeaders($admin));
        $resp->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $resp->headers->get('content-type'));
    }

    public function test_export_validates_dates(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/exports', [
            'type' => 'orders', 'format' => 'csv',
            'date_from' => '2026-12-31', 'date_to' => '2026-01-01',
        ], $this->authHeaders($admin))
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_export_unauthenticated_rejected(): void
    {
        $this->postJson('/api/exports', [
            'type' => 'orders', 'format' => 'csv',
            'date_from' => '2026-01-01', 'date_to' => '2026-12-31',
        ])->assertStatus(401)
          ->assertJsonStructure(['message']);
    }
}
