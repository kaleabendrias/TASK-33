<?php

namespace ApiTests\Integration;

use App\Application\Services\BookingService;
use App\Application\Services\SettlementService;
use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\GroupLeaderAssignment;
use App\Domain\Models\Order;
use App\Domain\Models\OrderLineItem;
use App\Domain\Models\Settlement;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use ApiTests\TestCase;

/**
 * End-to-end coverage of the cycle_type plumbing — verifies that the value
 * the admin submits at the API boundary lands intact on both the persisted
 * settlement row AND the commissions generated against it, and that bad
 * inputs are rejected at the controller layer.
 */
class CycleTypeIntegrationTest extends TestCase
{
    private function adminHeaders(): array
    {
        $admin = $this->createUser('admin');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($admin, Request::create('/'));
        return [
            'Authorization' => 'Bearer ' . $tokens['access_token'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    private function seedRevenueOrder(): void
    {
        $sa = ServiceArea::firstOrCreate(['slug' => 'cyc-sa'], ['name' => 'Cyc SA']);
        $leader = User::create([
            'username' => 'cyc_leader_' . mt_rand(),
            'password' => 'TestPass@12345!',
            'full_name' => 'Cyc Leader',
            'role' => 'group-leader',
        ]);
        GroupLeaderAssignment::create([
            'user_id' => $leader->id, 'service_area_id' => $sa->id, 'is_active' => true,
        ]);
        $item = BookableItem::firstOrCreate(
            ['name' => 'Cyc Item'],
            ['type' => 'room', 'daily_rate' => 200, 'tax_rate' => 0, 'capacity' => 5, 'is_active' => true],
        );
        $order = Order::create([
            'order_number' => 'ORD-CYC-' . mt_rand(),
            'user_id' => $leader->id,
            'group_leader_id' => $leader->id,
            'service_area_id' => $sa->id,
            'status' => 'completed',
            'subtotal' => 200, 'total' => 200,
            'confirmed_at' => '2026-10-15 10:00:00',
        ]);
        OrderLineItem::create([
            'order_id' => $order->id, 'bookable_item_id' => $item->id,
            'booking_date' => '2026-10-15', 'quantity' => 1, 'unit_price' => 200,
            'line_subtotal' => 200, 'line_tax' => 0, 'line_total' => 200,
        ]);
    }

    public function test_admin_generates_weekly_settlement(): void
    {
        $headers = $this->adminHeaders();
        $this->seedRevenueOrder();

        $resp = $this->postJson('/api/admin/settlements/generate', [
            'period_start' => '2026-10-01',
            'period_end'   => '2026-10-31',
            'cycle_type'   => 'weekly',
        ], $headers);

        $resp->assertStatus(201)
            ->assertJsonPath('data.cycle_type', 'weekly');

        // Persisted state matches what was sent
        $stl = Settlement::find($resp->json('data.id'));
        $this->assertEquals('weekly', $stl->cycle_type);

        // Commissions inherit the cadence
        $this->assertGreaterThanOrEqual(1, $stl->commissions()->count());
        foreach ($stl->commissions as $c) {
            $this->assertEquals('weekly', $c->cycle_type);
            $this->assertEquals($stl->id, $c->settlement_id);
        }
    }

    public function test_admin_generates_biweekly_settlement(): void
    {
        $headers = $this->adminHeaders();
        $this->seedRevenueOrder();

        $resp = $this->postJson('/api/admin/settlements/generate', [
            'period_start' => '2026-10-01',
            'period_end'   => '2026-10-31',
            'cycle_type'   => 'biweekly',
        ], $headers);

        $resp->assertStatus(201)
            ->assertJsonPath('data.cycle_type', 'biweekly');

        $stl = Settlement::find($resp->json('data.id'));
        $this->assertEquals('biweekly', $stl->cycle_type);

        foreach ($stl->commissions as $c) {
            $this->assertEquals('biweekly', $c->cycle_type);
        }
    }

    public function test_generate_rejects_missing_cycle_type(): void
    {
        $headers = $this->adminHeaders();
        $this->postJson('/api/admin/settlements/generate', [
            'period_start' => '2026-10-01',
            'period_end'   => '2026-10-31',
        ], $headers)->assertStatus(422);
    }

    public function test_generate_rejects_invalid_cycle_type(): void
    {
        $headers = $this->adminHeaders();
        $this->postJson('/api/admin/settlements/generate', [
            'period_start' => '2026-10-01',
            'period_end'   => '2026-10-31',
            'cycle_type'   => 'monthly',
        ], $headers)->assertStatus(422);
    }

    public function test_service_layer_rejects_invalid_cycle_type_directly(): void
    {
        $svc = app(SettlementService::class);
        $this->expectException(\InvalidArgumentException::class);
        $svc->generateSettlement('2026-10-01', '2026-10-31', 'monthly');
    }

    public function test_calculate_commissions_respects_cycle_type(): void
    {
        $svc = app(SettlementService::class);
        $this->seedRevenueOrder();

        $commissions = $svc->calculateCommissions('2026-10-01', '2026-10-31', 'biweekly');
        foreach ($commissions as $c) {
            $this->assertEquals('biweekly', $c->cycle_type);
        }
    }

    public function test_calculate_commissions_rejects_invalid_cycle_type(): void
    {
        $svc = app(SettlementService::class);
        $this->expectException(\InvalidArgumentException::class);
        $svc->calculateCommissions('2026-10-01', '2026-10-31', 'monthly');
    }
}
