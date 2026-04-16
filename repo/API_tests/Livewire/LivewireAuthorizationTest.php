<?php

namespace ApiTests\Livewire;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\Settlement;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Livewire\Booking\BookingIndex;
use App\Livewire\Orders\OrderIndex;
use App\Livewire\Orders\OrderShow;
use App\Livewire\Settlement\CommissionReport;
use App\Livewire\Settlement\SettlementIndex;
use Livewire\Livewire;
use ApiTests\TestCase;

/**
 * Drives Livewire components directly to assert authorization parity with the API.
 *
 * Each test seeds users and orders, then mounts a Livewire component as if the user
 * is authenticated by stuffing `auth_user` into the request attributes (matching the
 * pattern used by the JwtAuthenticate middleware).
 */
class LivewireAuthorizationTest extends TestCase
{
    private function actAs(User $user): void
    {
        // Use Laravel's auth system (Livewire test re-enters request lifecycle)
        $this->actingAs($user);
        request()->attributes->set('auth_user', $user);
    }

    private function makeOrder(User $owner, ?User $gl = null, string $status = 'confirmed'): Order
    {
        return Order::create([
            'order_number' => 'LV-' . mt_rand(),
            'user_id' => $owner->id,
            'group_leader_id' => $gl?->id,
            'status' => $status,
            'subtotal' => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);
    }

    // ── OrderShow ──────────────────────────────────────────────────────

    public function test_order_show_blocks_unrelated_user(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $order = $this->makeOrder($owner);

        $this->actAs($other);
        Livewire::test(OrderShow::class, ['orderId' => $order->id])->assertStatus(403);
    }

    public function test_order_show_allows_owner(): void
    {
        $owner = $this->createUser('user');
        $order = $this->makeOrder($owner);

        $this->actAs($owner);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id]);
        $component->assertSet('orderId', $order->id);
    }

    public function test_order_show_user_cannot_invoke_operational_action(): void
    {
        $user = $this->createUser('user');
        $order = $this->makeOrder($user);

        $this->actAs($user);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id]);
        $component->call('checkIn');
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_order_show_staff_with_profile_check_in_authorized(): void
    {
        $staff = $this->createUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff);

        $this->actAs($staff);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id]);
        $component->call('checkIn');
        // No gate-denial error message
        $this->assertStringNotContainsString('not authorized', $component->get('error'));
    }

    // ── OrderIndex ──────────────────────────────────────────────────────

    public function test_order_index_isolates_by_user(): void
    {
        // OrderIndex now goes through the in-process API layer; the
        // /orders endpoint applies tenant isolation server-side. We
        // create a real order owned by $u1 and assert the component
        // surfaces it. End-to-end tenant isolation (other users CAN'T
        // see this row) is exercised by OrderApiTest and the IDOR
        // suite — here we only assert the component proxies correctly.
        $u1 = $this->createUser('user');
        $this->makeOrder($u1, status: 'draft');
        $this->actAs($u1);

        $component = Livewire::test(OrderIndex::class);
        $orders = $component->viewData('orders');
        $this->assertEquals(1, $orders->total());
    }

    public function test_order_index_admin_sees_all(): void
    {
        $admin = $this->createUser('admin');
        $u1 = $this->createUser('user');
        $u2 = $this->createUser('user');
        $this->makeOrder($u1, status: 'draft');
        $this->makeOrder($u2, status: 'draft');
        $this->actAs($admin);

        $component = Livewire::test(OrderIndex::class);
        $orders = $component->viewData('orders');
        $this->assertGreaterThanOrEqual(2, $orders->total());
    }

    // ── CommissionReport ───────────────────────────────────────────────

    public function test_commission_report_blocks_regular_user(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(CommissionReport::class)->assertStatus(403);
    }

    public function test_commission_report_allows_group_leader(): void
    {
        $gl = $this->createUser('group-leader');
        $this->actAs($gl);
        Livewire::test(CommissionReport::class)->assertStatus(200);
    }

    public function test_commission_report_isolates_data(): void
    {
        $gl1 = $this->createUser('group-leader');
        $gl2 = $this->createUser('group-leader');
        $stl = Settlement::create([
            'reference' => 'STL-LV', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        Commission::create([
            'group_leader_id' => $gl2->id, 'settlement_id' => $stl->id,
            'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-31', 'cycle_type' => 'weekly',
            'attributed_revenue' => 100, 'commission_rate' => 0.1, 'commission_amount' => 10,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-02-05',
        ]);

        $this->actAs($gl1);
        $component = Livewire::test(CommissionReport::class, ['dateFrom' => '2026-01-01', 'dateTo' => '2026-01-31']);
        $commissions = $component->viewData('commissions');
        // Either zero rows (gl1 has none) or all rows belong to gl1 — never gl2's row.
        $this->assertNotNull($commissions);
        foreach ($commissions as $c) {
            $this->assertEquals($gl1->id, $c->group_leader_id);
        }
        // Confirm gl2's row is NOT visible.
        $ids = collect($commissions)->pluck('group_leader_id')->all();
        $this->assertNotContains($gl2->id, $ids);
    }

    // ── SettlementIndex ─────────────────────────────────────────────────

    public function test_settlement_index_blocks_regular_user(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(SettlementIndex::class)->assertStatus(403);
    }

    public function test_settlement_index_isolates_by_group_leader(): void
    {
        $gl1 = $this->createUser('group-leader');
        $gl2 = $this->createUser('group-leader');
        $stl = Settlement::create([
            'reference' => 'STL-LV2', 'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0, 'status' => 'draft',
        ]);
        Commission::create([
            'group_leader_id' => $gl2->id, 'settlement_id' => $stl->id,
            'cycle_start' => '2026-02-01', 'cycle_end' => '2026-02-28', 'cycle_type' => 'weekly',
            'attributed_revenue' => 100, 'commission_rate' => 0.1, 'commission_amount' => 10,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-03-05',
        ]);

        $this->actAs($gl1);
        $component = Livewire::test(SettlementIndex::class);
        // Items are plain associative arrays from the API JSON; the
        // settlement belonging to gl2 must NOT appear in gl1's view.
        $refs = collect($component->viewData('settlements')->items())
            ->pluck('reference')
            ->all();
        $this->assertNotContains('STL-LV2', $refs);
    }

    // ── BookingIndex ────────────────────────────────────────────────────

    public function test_booking_index_authenticated_user_sees_active_items(): void
    {
        // BookingIndex routes through the real /bookings/items endpoint;
        // active-only filtering is enforced server-side. Create a real
        // visible row and assert it's surfaced through the component.
        BookableItem::create([
            'type' => 'room', 'name' => 'LV-Visible',
            'hourly_rate' => 30, 'daily_rate' => 100, 'tax_rate' => 0.1,
            'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);

        $component = Livewire::test(BookingIndex::class);
        $items = collect($component->viewData('items')->items());
        $this->assertGreaterThanOrEqual(1, $items->count());
        $this->assertContains('LV-Visible', $items->pluck('name')->all());
    }
}
