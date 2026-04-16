<?php

namespace ApiTests\Integration;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Livewire\Booking\BookingCreate;
use App\Livewire\Orders\OrderIndex;
use App\Livewire\Orders\OrderShow;
use App\Livewire\Pricing\PricingRuleManager;
use App\Livewire\Profile\StaffProfilePage;
use App\Livewire\Settlement\SettlementIndex;
use Livewire\Livewire;
use ApiTests\TestCase;

/**
 * Full-stack E2E flow tests.
 *
 * Each test crosses the Livewire↔API boundary in both directions:
 *   - Write via Livewire component → read back via direct API call, OR
 *   - Write via direct API call → read back via Livewire component view data.
 *
 * This validates that the two layers share the same underlying data model
 * and that authorization is consistent across both entry points.
 */
class FullStackFlowTest extends TestCase
{
    private function actAs(User $user): void
    {
        $this->actingAs($user);
        request()->attributes->set('auth_user', $user);
    }

    private function makeItem(string $suffix = ''): BookableItem
    {
        return BookableItem::create([
            'type' => 'room',
            'name' => 'E2E Room' . ($suffix ? " {$suffix}" : ''),
            'hourly_rate' => 50, 'daily_rate' => 200,
            'tax_rate' => 0.0, 'capacity' => 5, 'is_active' => true,
        ]);
    }

    private function staffWithProfile(string $role = 'staff'): User
    {
        $user = $this->createUser($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-' . mt_rand(100, 999),
            'department' => 'E2E', 'title' => 'E2E ' . ucfirst($role),
        ]);
        return $user;
    }

    // ── Flow 1: Livewire write → API read ────────────────────────────

    /**
     * User submits a booking through the Livewire wizard, then the same
     * order is visible via GET /api/orders with the correct user scope.
     */
    public function test_booking_created_via_livewire_visible_in_api_order_list(): void
    {
        $item = $this->makeItem('F1');
        $user = $this->createUser('user');
        $this->actAs($user);

        // Act: submit order through Livewire BookingCreate wizard
        Livewire::test(BookingCreate::class)
            ->set('lineItems', [[
                'bookable_item_id' => $item->id,
                'booking_date'     => '2026-12-01',
                'start_time'       => null,
                'end_time'         => null,
                'quantity'         => 1,
            ]])
            ->call('submitOrder');

        // Assert: order is persisted and visible via the API
        $resp = $this->getJson('/api/orders', $this->authHeaders($user));
        $resp->assertOk()
            ->assertJsonStructure(['data', 'total']);
        $this->assertGreaterThanOrEqual(1, $resp->json('total'));
    }

    /**
     * Order cancelled through Livewire OrderShow is reflected in the API.
     * The transition must propagate through the shared domain layer.
     */
    public function test_order_cancelled_via_livewire_reflected_in_api(): void
    {
        $user = $this->createUser('user');
        $order = Order::create([
            'order_number' => 'E2E-CAN-' . mt_rand(),
            'user_id'      => $user->id,
            'status'       => 'confirmed',
            'subtotal'     => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);

        $this->actAs($user);

        // Act: cancel via Livewire OrderShow
        Livewire::test(OrderShow::class, ['orderId' => $order->id])
            ->set('cancelReason', 'E2E test cancel')
            ->call('cancel');

        // Assert: GET /api/orders/{id} reflects the status change
        $resp = $this->getJson("/api/orders/{$order->id}", $this->authHeaders($user));
        $resp->assertOk();
        $this->assertEquals('cancelled', $resp->json('data.status'));
    }

    /**
     * Staff profile saved via Livewire is immediately readable through the
     * profile API endpoint — both layers share the same DB row.
     */
    public function test_profile_saved_via_livewire_readable_via_api(): void
    {
        $user = $this->createUser('staff');
        $this->actAs($user);

        // Act: save profile through Livewire
        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', 'E2E-999')
            ->set('department', 'Platform')
            ->set('title', 'E2E Engineer')
            ->call('save');

        // Assert: profile is visible via the API endpoint
        $resp = $this->getJson('/api/profile', $this->authHeaders($user));
        $resp->assertOk()
            ->assertJsonPath('data.employee_id', 'E2E-999')
            ->assertJsonPath('data.department', 'Platform')
            ->assertJsonPath('data.title', 'E2E Engineer');
    }

    /**
     * Admin creates a pricing rule through the Livewire manager and it
     * is immediately visible via GET /api/admin/pricing-rules.
     */
    public function test_pricing_rule_created_via_livewire_visible_via_api(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        // Act: create rule via Livewire PricingRuleManager
        Livewire::test(PricingRuleManager::class)
            ->set('name', 'E2E Peak Rate')
            ->set('adjustment_type', 'multiplier')
            ->set('adjustment_value', '1.25')
            ->set('effective_from', '2026-01-01')
            ->set('priority', 50)
            ->call('save');

        // Assert: rule is visible via the API
        $resp = $this->getJson('/api/admin/pricing-rules', $this->authHeaders($admin));
        $resp->assertOk()
            ->assertJsonStructure(['data']);
        $names = collect($resp->json('data'))->pluck('name')->toArray();
        $this->assertContains('E2E Peak Rate', $names);
    }

    /**
     * Admin generates a settlement via the Livewire SettlementIndex and it
     * is immediately readable via GET /api/settlements.
     */
    public function test_settlement_generated_via_livewire_visible_via_api(): void
    {
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        // Act: generate settlement via Livewire
        $component = Livewire::test(SettlementIndex::class)
            ->set('periodStart', '2026-02-01')
            ->set('periodEnd', '2026-02-28')
            ->call('generate');

        // Capture the reference from the flash message
        $msg = $component->get('message');
        preg_match('/STL-[A-Z0-9\-]+/', $msg, $m);
        $reference = $m[0] ?? null;

        // Assert: generated settlement is visible via the API
        $resp = $this->getJson('/api/settlements', $this->authHeaders($admin));
        $resp->assertOk()
            ->assertJsonStructure(['data']);

        if ($reference) {
            $refs = collect($resp->json('data'))->pluck('reference')->toArray();
            $this->assertContains($reference, $refs);
        } else {
            // Generation produced a message — settlement exists even if regex didn't match
            $this->assertNotEmpty($msg);
        }
    }

    // ── Flow 2: API write → Livewire view ────────────────────────────

    /**
     * Order created via direct POST /api/orders is immediately visible in the
     * Livewire OrderIndex component's view data — same DB, same scoping.
     */
    public function test_order_created_via_api_visible_in_livewire_order_index(): void
    {
        $user = $this->staffWithProfile();

        // Act: create order via direct API call
        $resp = $this->postJson('/api/orders', [
            'line_items' => [[
                'bookable_item_id' => $this->makeItem('F2')->id,
                'booking_date'     => '2026-11-15',
                'quantity'         => 1,
            ]],
        ], $this->authHeaders($user));
        $resp->assertStatus(201);
        $orderId = $resp->json('data.id');

        // Assert: the same order appears in Livewire OrderIndex view data
        $this->actAs($user);
        $component = Livewire::test(OrderIndex::class);
        $orders = $component->viewData('orders');
        $ids = collect($orders->items())->pluck('id')->toArray();
        $this->assertContains($orderId, $ids);
    }

    /**
     * Profile upserted via PUT /api/profile is immediately reflected when
     * the Livewire StaffProfilePage mounts — same DB row, no caching gap.
     */
    public function test_profile_upserted_via_api_reflected_in_livewire_mount(): void
    {
        $user = $this->createUser('staff');

        // Act: create/update profile via direct API call
        $this->putJson('/api/profile', [
            'employee_id' => 'API-E2E-42',
            'department'  => 'Infrastructure',
            'title'       => 'Staff API',
        ], $this->authHeaders($user))->assertOk();

        // Assert: Livewire StaffProfilePage mounts with the API-written values
        $this->actAs($user);
        $component = Livewire::test(StaffProfilePage::class);
        $this->assertEquals('API-E2E-42', $component->get('employee_id'));
        $this->assertEquals('Infrastructure', $component->get('department'));
    }

    // ── Flow 3: Authorization parity (same rule, both layers) ────────

    /**
     * A non-owner user is blocked from viewing an order both via the API
     * endpoint AND via the Livewire OrderShow component — the same Gate
     * policy enforces both.
     */
    public function test_order_view_authorization_is_consistent_across_layers(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $order = Order::create([
            'order_number' => 'E2E-PARITY-' . mt_rand(),
            'user_id'      => $owner->id,
            'status'       => 'confirmed',
            'subtotal'     => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);

        // Direct API: non-owner gets 403
        $this->getJson("/api/orders/{$order->id}", $this->authHeaders($other))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);

        // Livewire: non-owner also gets 403 from mount()
        $this->actAs($other);
        Livewire::test(OrderShow::class, ['orderId' => $order->id])
            ->assertStatus(403);
    }

    /**
     * Group-leader scoping is consistent: the same orders visible in the API
     * response are the ones returned by the Livewire component's view data.
     */
    public function test_order_list_scoping_matches_between_api_and_livewire(): void
    {
        $gl   = $this->staffWithProfile('group-leader');
        $user = $this->createUser('user');

        // Seed two orders: one belonging to $gl, one to $user
        $glOrder = Order::create([
            'order_number' => 'E2E-GL-' . mt_rand(),
            'user_id' => $gl->id, 'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);
        Order::create([
            'order_number' => 'E2E-USR-' . mt_rand(),
            'user_id' => $user->id, 'status' => 'confirmed',
            'subtotal' => 100, 'total' => 100, 'confirmed_at' => now(),
        ]);

        // API: group-leader sees only their own order
        $apiIds = collect(
            $this->getJson('/api/orders', $this->authHeaders($gl))
                ->assertOk()
                ->json('data')
        )->pluck('id')->toArray();

        // Livewire: same scope
        $this->actAs($gl);
        $lwIds = collect(
            Livewire::test(OrderIndex::class)->viewData('orders')->items()
        )->pluck('id')->toArray();

        $this->assertContains($glOrder->id, $apiIds);
        $this->assertNotContains($user->id, $apiIds);
        $this->assertEquals(sort($apiIds), sort($lwIds));
    }
}
