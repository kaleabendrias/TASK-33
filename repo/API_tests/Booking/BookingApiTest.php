<?php

namespace ApiTests\Booking;

use App\Domain\Models\BookableItem;
use App\Domain\Models\Coupon;
use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\Role;
use ApiTests\TestCase;

class BookingApiTest extends TestCase
{
    private BookableItem $room;
    private BookableItem $consumable;

    protected function setUp(): void
    {
        parent::setUp();
        $sa = ServiceArea::create(['name' => 'BK SA', 'slug' => 'bk-sa']);
        $this->room = BookableItem::create([
            'type' => 'room', 'name' => 'API Room', 'hourly_rate' => 40, 'daily_rate' => 250,
            'tax_rate' => 0.1000, 'capacity' => 1, 'is_active' => true, 'service_area_id' => $sa->id,
        ]);
        $this->consumable = BookableItem::create([
            'type' => 'consumable', 'name' => 'API Supply', 'unit_price' => 10,
            'tax_rate' => 0.0500, 'stock' => 20, 'is_active' => true,
        ]);
        Coupon::create([
            'code' => 'API10', 'discount_type' => 'percentage', 'discount_value' => 10,
            'min_order_amount' => 0, 'valid_from' => now()->subDay(), 'is_active' => true,
        ]);

        // Setup permissions for staff
        foreach (['resources.create', 'resources.update', 'resources.transition'] as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        }
    }

    public function test_list_service_areas_authenticated(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/service-areas', $this->authHeaders($user))->assertOk();
    }

    public function test_list_resources_authenticated(): void
    {
        $user = $this->createUser('user');
        $role = Role::create(['name' => 'R', 'slug' => 'r-' . mt_rand(), 'level' => 1]);
        $sa = ServiceArea::first();
        \App\Domain\Models\Resource::create([
            'name' => 'ListR', 'service_area_id' => $sa->id, 'role_id' => $role->id, 'capacity_hours' => 100, 'is_available' => true,
        ]);
        $this->getJson('/api/resources', $this->authHeaders($user))->assertOk();
    }

    public function test_create_resource_with_status(): void
    {
        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = Role::create(['name' => 'Cr', 'slug' => 'cr-' . mt_rand(), 'level' => 1]);
        $response = $this->postJson('/api/resources', [
            'name' => 'StatusRes', 'service_area_id' => $sa->id, 'role_id' => $role->id,
        ], $this->authHeaders($staff));
        $response->assertStatus(201)->assertJsonPath('data.status', 'available');
    }

    public function test_resource_transition(): void
    {
        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = Role::create(['name' => 'Tr', 'slug' => 'tr-' . mt_rand(), 'level' => 1]);
        $r = \App\Domain\Models\Resource::create([
            'name' => 'TransRes', 'service_area_id' => $sa->id, 'role_id' => $role->id,
            'capacity_hours' => 100, 'is_available' => true, 'status' => 'available',
        ]);
        $this->postJson("/api/resources/{$r->id}/transition", [
            'status' => 'reserved', 'reason' => 'Approved',
        ], $this->authHeaders($staff))->assertOk()->assertJsonPath('data.status', 'reserved');
    }

    public function test_invalid_resource_transition(): void
    {
        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = Role::create(['name' => 'IT', 'slug' => 'it-' . mt_rand(), 'level' => 1]);
        $r = \App\Domain\Models\Resource::create([
            'name' => 'BadTrans', 'service_area_id' => $sa->id, 'role_id' => $role->id,
            'capacity_hours' => 100, 'is_available' => true, 'status' => 'available',
        ]);
        $this->postJson("/api/resources/{$r->id}/transition", [
            'status' => 'in_use',
        ], $this->authHeaders($staff))->assertStatus(422);
    }

    public function test_pricing_baseline_crud(): void
    {
        $perm = Permission::firstOrCreate(['slug' => 'pricing-baselines.create']);
        RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $perm->id]);

        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = Role::create(['name' => 'PB', 'slug' => 'pb-' . mt_rand(), 'level' => 1]);

        $this->postJson('/api/pricing-baselines', [
            'service_area_id' => $sa->id, 'role_id' => $role->id,
            'hourly_rate' => 75, 'effective_from' => '2026-01-01',
        ], $this->authHeaders($staff))->assertStatus(201);
    }

    public function test_pricing_baseline_below_minimum_fails(): void
    {
        $perm = Permission::firstOrCreate(['slug' => 'pricing-baselines.create']);
        RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $perm->id]);

        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = Role::create(['name' => 'PL', 'slug' => 'pl-' . mt_rand(), 'level' => 1]);

        $this->postJson('/api/pricing-baselines', [
            'service_area_id' => $sa->id, 'role_id' => $role->id,
            'hourly_rate' => 5, 'effective_from' => '2026-01-01',
        ], $this->authHeaders($staff))->assertStatus(422);
    }

    public function test_health_endpoint_public(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_unauthenticated_data_access_rejected(): void
    {
        $this->getJson('/api/service-areas', ['Accept' => 'application/json'])->assertStatus(401);
    }
}
