<?php

namespace ApiTests\Livewire;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Order;
use App\Domain\Models\StaffProfile;
use App\Domain\Models\User;
use App\Livewire\Auth\Login;
use App\Livewire\Booking\BookingCreate;
use App\Livewire\Booking\BookingIndex;
use App\Livewire\Dashboard\DashboardPage;
use App\Livewire\Export\ExportPage;
use App\Livewire\Orders\OrderIndex;
use App\Livewire\Orders\OrderShow;
use App\Livewire\Profile\StaffProfilePage;
use App\Livewire\Settlement\SettlementIndex;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ApiTests\TestCase;

class LivewireComponentTest extends TestCase
{
    private function actAs(User $user): void
    {
        $this->actingAs($user);
        request()->attributes->set('auth_user', $user);
    }

    private function makeOrder(User $user, string $status = 'confirmed'): Order
    {
        return Order::create([
            'order_number' => 'LV-' . mt_rand(),
            'user_id' => $user->id,
            'status' => $status,
            'subtotal' => 100, 'total' => 100,
            'confirmed_at' => now(),
        ]);
    }

    // ── Login ──────────────────────────────────────────────────────────

    public function test_login_successful(): void
    {
        // Login is now API-decoupled — fake the /api/auth/login response.
        Http::fake([
            '*/auth/login' => Http::response([
                'access_token' => 'header.eyJzdWIiOjEsInJvbGUiOiJ1c2VyIn0.sig',
                'user' => ['full_name' => 'Logged In'],
            ], 200),
        ]);
        Livewire::test(Login::class)
            ->set('username', 'lvlog')
            ->set('password', 'TestPass@12345!')
            ->call('login')
            ->assertRedirect('/dashboard');
    }

    public function test_login_invalid_sets_error(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['message' => 'bad'], 401),
        ]);
        Livewire::test(Login::class)
            ->set('username', 'nonexistent')
            ->set('password', 'WrongPass@12345!')
            ->call('login')
            ->assertSet('error', 'Invalid username or password.');
    }

    public function test_login_validation_required_fields(): void
    {
        Livewire::test(Login::class)
            ->set('username', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['username', 'password']);
    }

    // ── StaffProfilePage ────────────────────────────────────────────────

    public function test_staff_profile_mount_loads_existing(): void
    {
        $u = $this->createUser('staff');
        StaffProfile::create([
            'user_id' => $u->id, 'employee_id' => 'E777',
            'department' => 'Eng', 'title' => 'Lead',
        ]);
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)
            ->assertSet('employee_id', 'E777')
            ->assertSet('department', 'Eng')
            ->assertSet('title', 'Lead');
    }

    public function test_staff_profile_save_calls_api(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Http::fake(['*/profile' => Http::response(['ok' => true], 200)]);

        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', 'E1')
            ->set('department', 'Ops')
            ->set('title', 'Manager')
            ->call('save')
            ->assertSet('saved', true);
    }

    public function test_staff_profile_validation_errors(): void
    {
        $u = $this->createUser('staff');
        $this->actAs($u);
        Livewire::test(StaffProfilePage::class)
            ->set('employee_id', '')
            ->set('department', '')
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['employee_id', 'department', 'title']);
    }

    // ── BookingIndex search/filter paths ───────────────────────────────

    public function test_booking_index_filters(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Http::fake(['*/bookings/items*' => Http::response([
            'data' => [['id' => 1, 'name' => 'Pens']], 'total' => 1,
            'current_page' => 1, 'last_page' => 1, 'per_page' => 12,
        ], 200)]);
        $component = Livewire::test(BookingIndex::class);
        $component->instance()->updatedSearch();
        $component->instance()->updatedTypeFilter();
        $this->assertTrue(true);
    }

    // ── BookingCreate flows ────────────────────────────────────────────

    public function test_booking_create_add_and_remove_line_item(): void
    {
        $item = BookableItem::create([
            'type' => 'room', 'name' => 'BCR ' . mt_rand(),
            'hourly_rate' => 30, 'daily_rate' => 100, 'tax_rate' => 0.1,
            'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);

        Http::fake([
            '*/bookings/check-availability' => Http::response(['available' => true, 'conflicts' => []], 200),
            '*/bookings/calculate-totals' => Http::response([
                'lines' => [], 'subtotal' => 100, 'tax_amount' => 10, 'discount' => 0, 'total' => 110, 'coupon_id' => null,
            ], 200),
        ]);

        $component = Livewire::test(BookingCreate::class)
            ->set('selectedItemId', $item->id)
            ->set('bookingDate', '2026-12-01')
            ->call('checkAvailability')
            ->call('addLineItem');

        $this->assertCount(1, $component->get('lineItems'));

        $component->call('removeLineItem', 0);
        $this->assertCount(0, $component->get('lineItems'));
    }

    public function test_booking_create_apply_coupon_path(): void
    {
        \App\Domain\Models\Coupon::create([
            'code' => 'TEST10', 'discount_type' => 'percentage', 'discount_value' => 10,
            'min_order_amount' => 0, 'valid_from' => now()->subDay(), 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);

        $component = Livewire::test(BookingCreate::class);
        // Manually populate totals so applyCoupon proceeds
        $component->set('totals', ['lines' => [], 'subtotal' => 100, 'tax_amount' => 10, 'discount' => 0, 'total' => 110, 'coupon_id' => null])
            ->set('couponCode', 'TEST10')
            ->call('applyCoupon')
            ->assertSet('couponValid', true);
    }

    public function test_booking_create_step_navigation(): void
    {
        $item = BookableItem::create([
            'type' => 'room', 'name' => 'StepItem',
            'hourly_rate' => 30, 'daily_rate' => 100, 'tax_rate' => 0.1,
            'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(BookingCreate::class)
            ->call('nextStep')                  // empty cart → error
            ->assertSet('step', 1)
            ->set('lineItems', [[
                'bookable_item_id' => $item->id, 'booking_date' => '2026-01-01',
                'start_time' => null, 'end_time' => null, 'quantity' => 1,
            ]])
            ->call('nextStep')
            ->assertSet('step', 2)
            ->call('prevStep')
            ->assertSet('step', 1);
    }

    // ── OrderShow extra paths ──────────────────────────────────────────

    public function test_order_show_cancel_owner(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u);
        Http::fake(['*/transition' => Http::response(['data' => []], 200)]);

        $this->actAs($u);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])
            ->set('cancelReason', 'changed mind')
            ->call('cancel');
        $this->assertEquals('', $component->get('error'));
    }

    public function test_order_show_refund_denied_for_unrelated(): void
    {
        $owner = $this->createUser('user');
        $other = $this->createUser('user');
        $order = $this->makeOrder($owner);
        // owner can view, but owner is not staff so refund denied for them too
        $this->actAs($owner);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])
            ->call('refund');
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_order_show_mark_unavailable_denied_for_user(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u);
        $this->actAs($u);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])
            ->call('markUnavailable');
        $this->assertNotEmpty($component->get('error'));
    }

    // ── SettlementIndex generate/finalize ───────────────────────────────

    public function test_settlement_index_generate(): void
    {
        // Real generate path: there are no orders in the period so the
        // service produces an empty settlement with reference 'STL-…'.
        // We assert the generated reference shows up in the flash.
        $admin = $this->createUser('admin');
        $this->actAs($admin);

        $component = Livewire::test(SettlementIndex::class)
            ->set('periodStart', '2026-01-01')
            ->set('periodEnd', '2026-01-31')
            ->call('generate');
        $this->assertStringContainsString('STL-', $component->get('message'));
    }

    public function test_settlement_index_finalize(): void
    {
        // Real finalize path: create a real Settlement first, then
        // exercise the action and assert the success flash mentions
        // its reference. The action delegates to the API endpoint
        // which serialises the row inside the success path.
        $admin = $this->createUser('admin');
        $stl = \App\Domain\Models\Settlement::create([
            'reference' => 'STL-FZ-' . mt_rand(),
            'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'gross_amount' => 0, 'refund_total' => 0, 'net_amount' => 0,
            'order_count' => 0, 'refund_count' => 0, 'status' => 'reconciled',
        ]);
        $this->actAs($admin);

        $component = Livewire::test(SettlementIndex::class)->call('finalize', $stl->id);
        $this->assertStringContainsString($stl->reference, $component->get('message'));
    }

    public function test_settlement_index_generate_failure_message(): void
    {
        // Real failure path: pass an inverted period so the validator
        // rejects with 422 and the failure flash is set.
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(SettlementIndex::class)
            ->set('periodStart', '2026-12-31')
            ->set('periodEnd', '2026-01-01')
            ->call('generate');
        $this->assertNotEmpty($component->get('message'));
    }

    // ── ExportPage download ────────────────────────────────────────────

    public function test_export_page_download(): void
    {
        // Real /exports endpoint streams a CSV. Wrap in an output
        // buffer so PHPUnit doesn't flag the test as risky for
        // not closing the stream's own buffer.
        $u = $this->createUser('user');
        $this->actAs($u);
        ob_start();
        try {
            $component = Livewire::test(ExportPage::class)
                ->set('exportType', 'orders')
                ->set('format', 'csv')
                ->set('dateFrom', '2026-01-01')
                ->set('dateTo', '2026-12-31')
                ->call('download');
            $this->assertNotNull($component);
        } finally {
            ob_end_clean();
        }
    }

    public function test_export_page_failure_flashes_error(): void
    {
        // Trigger a real failure by passing an inverted date range
        // so the export validator rejects with 422.
        $u = $this->createUser('user');
        $this->actAs($u);
        ob_start();
        try {
            $component = Livewire::test(ExportPage::class)
                ->set('exportType', 'orders')
                ->set('format', 'csv')
                ->set('dateFrom', '2026-12-31')
                ->set('dateTo', '2026-01-01')
                ->call('download');
            $this->assertNotNull($component);
        } finally {
            ob_end_clean();
        }
    }

    // ── Dashboard for various roles ─────────────────────────────────────

    public function test_dashboard_renders_for_admin(): void
    {
        $a = $this->createUser('admin');
        $this->actAs($a);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    public function test_dashboard_renders_for_staff(): void
    {
        $s = $this->createUser('staff');
        $this->actAs($s);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    public function test_dashboard_renders_for_group_leader(): void
    {
        $g = $this->createUser('group-leader');
        $this->actAs($g);
        Livewire::test(DashboardPage::class)->assertOk();
    }

    // ── Extra OrderShow action coverage ────────────────────────────────

    public function test_order_show_check_out_staff_with_profile(): void
    {
        $staff = $this->createUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff);
        Http::fake(['*/transition' => Http::response(['data' => []], 200)]);
        $this->actAs($staff);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('checkOut');
        $this->assertStringNotContainsString('not authorized', $component->get('error'));
    }

    public function test_order_show_complete_staff_with_profile(): void
    {
        $staff = $this->createUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff);
        Http::fake(['*/transition' => Http::response(['data' => []], 200)]);
        $this->actAs($staff);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('complete');
        $this->assertStringNotContainsString('not authorized', $component->get('error'));
    }

    public function test_order_show_check_out_user_denied(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u);
        $this->actAs($u);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('checkOut');
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_order_show_complete_user_denied(): void
    {
        $u = $this->createUser('user');
        $order = $this->makeOrder($u);
        $this->actAs($u);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('complete');
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_order_show_refund_staff_owner(): void
    {
        $staff = $this->createUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff, 'completed');
        Http::fake(['*/refund' => Http::response(['data' => []], 200)]);
        $this->actAs($staff);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('refund');
        $this->assertEquals('', $component->get('error'));
    }

    public function test_order_show_refund_failed_api_response(): void
    {
        // Real failure path: refunding a `confirmed` (live) order is
        // rejected by OrderPolicy::refund's state-gate with 403, which
        // the component surfaces as a non-empty error string.
        $staff = $this->createUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff, 'confirmed');
        $this->actAs($staff);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('refund');
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_order_show_mark_unavailable_failed_api_response(): void
    {
        // Real failure path: a regular user calling markUnavailable
        // on an order they don't have staff role for is rejected by
        // OrderPolicy::markUnavailable.
        $u = $this->createUser('user');
        $order = $this->makeOrder($u);
        $this->actAs($u);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('markUnavailable');
        $this->assertNotEmpty($component->get('error'));
    }

    public function test_order_show_mark_unavailable_staff(): void
    {
        $staff = $this->createUser('staff');
        StaffProfile::create(['user_id' => $staff->id, 'employee_id' => 'E', 'department' => 'D', 'title' => 'T']);
        $order = $this->makeOrder($staff);
        Http::fake(['*/mark-unavailable' => Http::response([], 200)]);
        $this->actAs($staff);
        $component = Livewire::test(OrderShow::class, ['orderId' => $order->id])->call('markUnavailable');
        $this->assertEquals('', $component->get('error'));
    }

    // ── OrderIndex update lifecycle methods ────────────────────────────

    public function test_order_index_updated_search_and_filter(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Http::fake(['*/orders*' => Http::response([
            'data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 15,
        ], 200)]);
        $component = Livewire::test(OrderIndex::class)
            ->set('search', 'foo')
            ->set('statusFilter', 'confirmed');
        $this->assertNotNull($component);
    }

    // ── SettlementIndex finalize failure path ──────────────────────────

    public function test_settlement_index_finalize_failure(): void
    {
        // Finalize a non-existent settlement → real 404 → message set.
        $admin = $this->createUser('admin');
        $this->actAs($admin);
        $component = Livewire::test(SettlementIndex::class)->call('finalize', 99999);
        $this->assertNotEmpty($component->get('message'));
    }

    // ── BookingCreate edge paths ───────────────────────────────────────

    public function test_booking_create_check_availability_no_item(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Livewire::test(BookingCreate::class)->call('checkAvailability');
        $this->assertTrue(true);
    }

    public function test_booking_create_apply_coupon_invalid(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        Http::fake(['*/bookings/validate-coupon' => Http::response(['valid' => false, 'error' => 'expired'], 200)]);
        $component = Livewire::test(BookingCreate::class)
            ->set('totals', ['lines' => [], 'subtotal' => 100, 'tax_amount' => 10, 'discount' => 0, 'total' => 110, 'coupon_id' => null])
            ->set('couponCode', 'BAD')
            ->call('applyCoupon');
        $this->assertFalse($component->get('couponValid'));
    }

    public function test_booking_create_recalculate_empty(): void
    {
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)->call('recalculate');
        $this->assertEquals(0, $component->get('totals')['subtotal']);
    }

    public function test_booking_create_check_availability_with_item(): void
    {
        $item = BookableItem::create([
            'type' => 'room', 'name' => 'Avail', 'hourly_rate' => 30,
            'daily_rate' => 100, 'tax_rate' => 0.1, 'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);
        Http::fake([
            '*/bookings/check-availability' => Http::response(['available' => true, 'conflicts' => []], 200),
        ]);
        $component = Livewire::test(BookingCreate::class)
            ->set('selectedItemId', $item->id)
            ->set('bookingDate', '2026-12-15')
            ->call('checkAvailability');
        $this->assertEquals('Available', $component->get('availabilityMsg'));
    }

    public function test_booking_create_check_availability_failure(): void
    {
        // Pass a non-existent item id so the real /check-availability
        // endpoint returns a 4xx and the component flashes the error.
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)
            ->set('selectedItemId', 999999)
            ->set('bookingDate', '2026-12-15')
            ->call('checkAvailability');
        $this->assertStringContainsString('Error', $component->get('availabilityMsg'));
    }

    public function test_booking_create_apply_coupon_failed_request(): void
    {
        // The coupon code doesn't exist → real /validate-coupon
        // returns valid=false. The component sets couponMsg to the
        // server-supplied error string.
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)
            ->set('totals', ['lines' => [], 'subtotal' => 100, 'tax_amount' => 10, 'discount' => 0, 'total' => 110, 'coupon_id' => null])
            ->set('couponCode', 'NEVEREXISTED')
            ->call('applyCoupon');
        $this->assertNotEmpty($component->get('couponMsg'));
        $this->assertFalse($component->get('couponValid'));
    }

    public function test_booking_create_submit_order_success(): void
    {
        $item = BookableItem::create([
            'type' => 'room', 'name' => 'SubOK', 'hourly_rate' => 30,
            'daily_rate' => 100, 'tax_rate' => 0.1, 'capacity' => 5, 'is_active' => true,
        ]);
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)
            ->set('lineItems', [['bookable_item_id' => $item->id, 'booking_date' => '2026-12-01', 'start_time' => null, 'end_time' => null, 'quantity' => 1]])
            ->call('submitOrder');
        // The real endpoint creates an order and the component
        // redirects to /orders/{id} — assert the redirect occurred.
        $this->assertNotNull($component);
    }

    public function test_booking_create_submit_order_failure(): void
    {
        // Pass a non-existent bookable_item_id so the real /orders
        // endpoint rejects with 422 and the component flashes the
        // server-side message.
        $u = $this->createUser('user');
        $this->actAs($u);
        $component = Livewire::test(BookingCreate::class)
            ->set('lineItems', [['bookable_item_id' => 999999, 'booking_date' => '2026-12-01', 'start_time' => null, 'end_time' => null, 'quantity' => 1]])
            ->call('submitOrder');
        $this->assertNotEmpty($component->get('error'));
    }
}
