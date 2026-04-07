<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\{Attachment, BookableItem, Commission, Coupon, GroupLeaderAssignment, Order, OrderLineItem, PricingBaseline, Refund, Resource, Role, ServiceArea, Settlement, StaffProfile, User};
use App\Infrastructure\Repositories\EloquentPermissionRepository;
use App\Infrastructure\Repositories\EloquentPricingBaselineRepository;
use App\Infrastructure\Repositories\EloquentResourceRepository;
use App\Infrastructure\Repositories\EloquentRoleRepository;
use App\Infrastructure\Repositories\EloquentServiceAreaRepository;
use UnitTests\TestCase;

class CastsAndMiscTest extends TestCase
{
    private ServiceArea $sa;
    private Role $role;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['username' => 'cast_user_' . mt_rand(), 'password' => 'TestPass@12345!', 'full_name' => 'C', 'role' => 'admin']);
        $this->sa = ServiceArea::create(['name' => 'CastSA', 'slug' => 'cast-sa-' . mt_rand()]);
        $this->role = Role::create(['name' => 'CastRole', 'slug' => 'cast-role-' . mt_rand(), 'level' => 2]);
    }

    public function test_bookable_item_casts(): void
    {
        $item = BookableItem::create(['type' => 'lab', 'name' => 'CL', 'hourly_rate' => 40.50, 'daily_rate' => 300.25, 'tax_rate' => 0.0825, 'capacity' => 5, 'is_active' => true]);
        $this->assertIsString($item->hourly_rate);
        $this->assertTrue($item->is_active);
    }

    public function test_coupon_casts(): void
    {
        $c = Coupon::create(['code' => 'CST' . mt_rand(), 'discount_type' => 'fixed', 'discount_value' => 15.50, 'valid_from' => '2025-06-01', 'valid_until' => '2026-12-31', 'is_active' => true]);
        $this->assertInstanceOf(\Carbon\Carbon::class, $c->valid_from);
        $this->assertTrue($c->is_active);
    }

    public function test_order_line_item_casts(): void
    {
        $item = BookableItem::create(['type' => 'room', 'name' => 'OLI', 'daily_rate' => 50, 'tax_rate' => 0, 'capacity' => 1, 'is_active' => true]);
        $order = Order::create(['order_number' => 'ORD-CST-' . mt_rand(), 'user_id' => $this->user->id, 'status' => 'confirmed', 'subtotal' => 50, 'total' => 50, 'confirmed_at' => now()]);
        $li = OrderLineItem::create(['order_id' => $order->id, 'bookable_item_id' => $item->id, 'booking_date' => '2026-05-01', 'quantity' => 2, 'unit_price' => 50, 'tax_rate' => 0.08, 'line_subtotal' => 100, 'line_tax' => 8, 'line_total' => 108]);
        $this->assertInstanceOf(\Carbon\Carbon::class, $li->booking_date);
    }

    public function test_refund_casts(): void
    {
        $order = Order::create(['order_number' => 'ORD-RFC-' . mt_rand(), 'user_id' => $this->user->id, 'status' => 'refunded', 'subtotal' => 100, 'total' => 100]);
        $r = Refund::create(['order_id' => $order->id, 'original_amount' => 100, 'cancellation_fee' => 20, 'refund_amount' => 80, 'is_full_refund' => false, 'staff_unavailable_override' => false, 'status' => 'processed', 'processed_at' => now()]);
        $this->assertFalse($r->is_full_refund);
        $this->assertInstanceOf(\Carbon\Carbon::class, $r->processed_at);
    }

    public function test_commission_casts(): void
    {
        $c = Commission::create(['group_leader_id' => $this->user->id, 'cycle_start' => '2026-01-01', 'cycle_end' => '2026-01-15', 'attributed_revenue' => 500, 'commission_rate' => 0.10, 'commission_amount' => 50, 'hold_until' => now()->addDays(3)]);
        $this->assertInstanceOf(\Carbon\Carbon::class, $c->cycle_start);
        $this->assertInstanceOf(\Carbon\Carbon::class, $c->hold_until);
    }

    public function test_settlement_casts(): void
    {
        $s = Settlement::create(['reference' => 'STL-CST-' . mt_rand(), 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'gross_amount' => 1000, 'net_amount' => 950, 'finalized_at' => now()]);
        $this->assertInstanceOf(\Carbon\Carbon::class, $s->period_start);
        $this->assertInstanceOf(\Carbon\Carbon::class, $s->finalized_at);
    }

    public function test_group_leader_assignment_cast(): void
    {
        $gla = GroupLeaderAssignment::create(['user_id' => $this->user->id, 'service_area_id' => $this->sa->id, 'is_active' => true]);
        $this->assertTrue($gla->is_active);
    }

    // Repository coverage
    public function test_service_area_repo_all(): void
    {
        $repo = new EloquentServiceAreaRepository();
        $all = $repo->all();
        $this->assertGreaterThanOrEqual(1, $all->count());
    }

    public function test_role_repo_all(): void
    {
        $repo = new EloquentRoleRepository();
        $this->assertGreaterThanOrEqual(1, $repo->all()->count());
    }

    public function test_resource_repo(): void
    {
        $resource = Resource::create(['name' => 'RR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $repo = new EloquentResourceRepository();
        $this->assertNotNull($repo->findOrFail($resource->id));
        $this->assertGreaterThanOrEqual(1, $repo->findByServiceArea($this->sa->id)->count());
        $updated = $repo->update($resource, ['name' => 'Updated']);
        $this->assertEquals('Updated', $updated->name);
    }

    public function test_pricing_baseline_repo(): void
    {
        PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => now()->subDay()]);
        $repo = new EloquentPricingBaselineRepository();
        $this->assertGreaterThanOrEqual(1, $repo->all()->count());
        $this->assertGreaterThanOrEqual(1, $repo->findActiveByServiceArea($this->sa->id)->count());
    }

    public function test_permission_repo_cache(): void
    {
        $perm = \App\Domain\Models\Permission::create(['slug' => 'cache.test']);
        \App\Domain\Models\RolePermission::create(['role' => 'staff', 'permission_id' => $perm->id]);
        $repo = new EloquentPermissionRepository();
        // Call twice to exercise cache path
        $perms1 = $repo->permissionsForRole('staff');
        $perms2 = $repo->permissionsForRole('staff');
        $this->assertEquals($perms1->toArray(), $perms2->toArray());
    }

    public function test_masks_for_non_admin_is_current_user(): void
    {
        $masker = new class {
            use \App\Domain\Traits\MasksForNonAdmin;
            public function testIsAdmin(): bool { return $this->isCurrentUserAdmin(); }
        };
        // No auth_user on request = not admin
        $this->assertFalse($masker->testIsAdmin());
    }

    public function test_has_attachments_trait(): void
    {
        $resource = Resource::create(['name' => 'ATR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $this->assertCount(0, $resource->attachments);
    }

    public function test_image_compressor_no_gd_graceful(): void
    {
        // Test with non-image file - should return original size
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'not an image');
        $size = \App\Infrastructure\Export\ImageCompressor::compress($tmpFile);
        $this->assertEquals(strlen('not an image'), $size);
        unlink($tmpFile);
    }
}
