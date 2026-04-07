<?php

namespace ApiTests\Integration;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Commission;
use App\Domain\Models\Order;
use App\Domain\Models\OrderLineItem;
use App\Domain\Models\Settlement;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Infrastructure\Auth\JwtService;
use Illuminate\Http\Request;
use ApiTests\TestCase;

/**
 * End-to-end integration tests that exercise the **real** authentication
 * stack (JWT issuance + JwtAuthenticate / WebSessionAuth middleware) instead
 * of bypassing it via $this->actingAs(). The point is to catch defects that
 * only surface when the auth guard, request attributes, and Gate facade
 * have to interact for real.
 */
class SettlementAccessIntegrationTest extends TestCase
{
    private function createUserWithProfile(string $role): User
    {
        $u = $this->createUser($role);
        if ($role !== 'user') {
            StaffProfile::create([
                'user_id' => $u->id, 'employee_id' => 'E' . mt_rand(),
                'department' => 'Test', 'title' => $role,
            ]);
        }
        return $u;
    }

    private function bearerHeaders(User $user): array
    {
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($user, Request::create('/'));
        return [
            'Authorization' => 'Bearer ' . $tokens['access_token'],
            'Accept' => 'application/json',
        ];
    }

    private function makeOrderForUser(User $user, string $confirmedAt = '2026-06-15 10:00:00'): Order
    {
        $item = BookableItem::firstOrCreate(
            ['name' => 'Stl Item'],
            ['type' => 'room', 'daily_rate' => 100, 'tax_rate' => 0, 'capacity' => 5, 'is_active' => true],
        );
        $order = Order::create([
            'order_number' => 'ORD-INT-' . mt_rand(),
            'user_id' => $user->id,
            'status' => 'completed',
            'subtotal' => 100, 'total' => 100,
            'confirmed_at' => $confirmedAt,
        ]);
        OrderLineItem::create([
            'order_id' => $order->id, 'bookable_item_id' => $item->id,
            'booking_date' => '2026-06-15', 'quantity' => 1, 'unit_price' => 100,
            'line_subtotal' => 100, 'line_tax' => 0, 'line_total' => 100,
        ]);
        return $order;
    }

    private function makeSettlement(string $start, string $end, string $cycleType = 'weekly'): Settlement
    {
        return Settlement::create([
            'reference' => 'STL-INT-' . mt_rand(),
            'period_start' => $start, 'period_end' => $end,
            'gross_amount' => 100, 'refund_total' => 0, 'net_amount' => 100,
            'order_count' => 1, 'refund_count' => 0,
            'status' => 'draft', 'cycle_type' => $cycleType,
        ]);
    }

    // ── Staff settlement read access (real JWT) ────────────────────────

    public function test_staff_with_profile_can_list_settlements_via_real_jwt(): void
    {
        $staff = $this->createUserWithProfile('staff');
        $this->makeOrderForUser($staff);
        $this->makeSettlement('2026-06-01', '2026-06-30');

        $resp = $this->getJson('/api/settlements', $this->bearerHeaders($staff));
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(1, $resp->json('total'));
    }

    public function test_staff_without_orders_in_period_sees_zero_settlements(): void
    {
        $staff = $this->createUserWithProfile('staff');
        // Settlement exists but staff has no orders within its period.
        $this->makeSettlement('2027-01-01', '2027-01-31');

        $resp = $this->getJson('/api/settlements', $this->bearerHeaders($staff));
        $resp->assertOk();
        $this->assertEquals(0, $resp->json('total'));
    }

    public function test_staff_cannot_see_other_staffs_settlements(): void
    {
        $alice = $this->createUserWithProfile('staff');
        $bob = $this->createUserWithProfile('staff');
        $this->makeOrderForUser($bob, '2026-06-10 10:00:00');
        $this->makeSettlement('2026-06-01', '2026-06-30');

        // Alice has no orders in the period — must see zero rows even though
        // a settlement exists that covers Bob's order.
        $resp = $this->getJson('/api/settlements', $this->bearerHeaders($alice));
        $resp->assertOk();
        $this->assertEquals(0, $resp->json('total'));
    }

    public function test_regular_user_blocked_from_settlements_endpoint(): void
    {
        $u = $this->createUser('user');
        $this->getJson('/api/settlements', $this->bearerHeaders($u))->assertStatus(403);
    }

    public function test_staff_without_profile_can_still_read_settlements(): void
    {
        // Reading a settlement summary is now a role-only operation —
        // profile completion is reserved for operational actions like
        // check-in. A staff member with an incomplete profile must still
        // be able to inspect their own financial summary.
        $staff = $this->createUser('staff'); // no profile
        $this->getJson('/api/settlements', $this->bearerHeaders($staff))
            ->assertOk();
    }

    public function test_admin_sees_every_settlement_unconditionally(): void
    {
        $admin = $this->createUser('admin');
        $this->makeSettlement('2026-06-01', '2026-06-30');
        $this->makeSettlement('2026-07-01', '2026-07-31');

        $resp = $this->getJson('/api/settlements', $this->bearerHeaders($admin));
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(2, $resp->json('total'));
    }

    public function test_group_leader_sees_only_attributed_settlements(): void
    {
        $gl1 = $this->createUserWithProfile('group-leader');
        $gl2 = $this->createUserWithProfile('group-leader');
        $stlA = $this->makeSettlement('2026-08-01', '2026-08-31');
        $stlB = $this->makeSettlement('2026-09-01', '2026-09-30');
        Commission::create([
            'group_leader_id' => $gl1->id, 'settlement_id' => $stlA->id,
            'cycle_start' => '2026-08-01', 'cycle_end' => '2026-08-31', 'cycle_type' => 'weekly',
            'attributed_revenue' => 100, 'commission_rate' => 0.1, 'commission_amount' => 10,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-09-05',
        ]);
        Commission::create([
            'group_leader_id' => $gl2->id, 'settlement_id' => $stlB->id,
            'cycle_start' => '2026-09-01', 'cycle_end' => '2026-09-30', 'cycle_type' => 'biweekly',
            'attributed_revenue' => 200, 'commission_rate' => 0.1, 'commission_amount' => 20,
            'order_count' => 1, 'status' => 'held', 'hold_until' => '2026-10-05',
        ]);

        $respA = $this->getJson('/api/settlements', $this->bearerHeaders($gl1));
        $refsA = collect($respA->json('data'))->pluck('reference');
        $this->assertTrue($refsA->contains($stlA->reference));
        $this->assertFalse($refsA->contains($stlB->reference));
    }

    // ── show endpoint: IDOR protection via scoped query ────────────────

    public function test_staff_show_returns_404_for_unrelated_settlement(): void
    {
        $staff = $this->createUserWithProfile('staff');
        $stl = $this->makeSettlement('2027-05-01', '2027-05-31');
        // Staff has no orders in this period — direct ID lookup must fail.
        $this->getJson("/api/settlements/{$stl->id}", $this->bearerHeaders($staff))
            ->assertStatus(404);
    }

    public function test_staff_show_returns_data_for_own_settlement(): void
    {
        $staff = $this->createUserWithProfile('staff');
        $this->makeOrderForUser($staff, '2027-05-15 10:00:00');
        $stl = $this->makeSettlement('2027-05-01', '2027-05-31');

        $this->getJson("/api/settlements/{$stl->id}", $this->bearerHeaders($staff))
            ->assertOk()
            ->assertJsonPath('data.id', $stl->id);
    }

    // ── Real session integration: Web layer auth guard ─────────────────

    public function test_web_session_login_hydrates_auth_guard_for_gate(): void
    {
        // The whole point of refactoring WebSessionAuth was that
        // Gate::allows() inside a Livewire render must find the user.
        // Hitting an authenticated /web route through the session driver
        // exercises the entire stack.
        $staff = $this->createUserWithProfile('staff');
        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($staff, Request::create('/'));

        $resp = $this->withSession([
            'jwt_token' => $tokens['access_token'],
            'auth_role' => 'staff',
            'auth_user_name' => $staff->full_name,
        ])->get('/dashboard');

        $resp->assertOk();
    }

    public function test_web_session_order_show_authorizes_owner(): void
    {
        // OrderShow's mount() calls Gate::allows('view', $order). This will
        // ONLY pass if WebSessionAuth populated the auth guard correctly.
        $staff = $this->createUserWithProfile('staff');
        $order = $this->makeOrderForUser($staff);

        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($staff, Request::create('/'));

        $resp = $this->withSession([
            'jwt_token' => $tokens['access_token'],
            'auth_role' => 'staff',
            'auth_user_name' => $staff->full_name,
        ])->get("/orders/{$order->id}");

        $resp->assertOk();
    }

    public function test_web_session_order_show_blocks_non_owner(): void
    {
        $owner = $this->createUserWithProfile('staff');
        $other = $this->createUserWithProfile('staff');
        $order = $this->makeOrderForUser($owner);

        $jwt = app(JwtService::class);
        $tokens = $jwt->issueToken($other, Request::create('/'));

        $this->withSession([
            'jwt_token' => $tokens['access_token'],
            'auth_role' => 'staff',
            'auth_user_name' => $other->full_name,
        ])->get("/orders/{$order->id}")
            ->assertStatus(403);
    }
}
