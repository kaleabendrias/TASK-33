<?php

namespace ApiTests\Controllers;

use App\Domain\Models\Permission;
use App\Domain\Models\PricingBaseline;
use App\Domain\Models\Role;
use App\Domain\Models\RolePermission;
use App\Domain\Models\ServiceArea;
use ApiTests\TestCase;

class PricingControllerTest extends TestCase
{
    private ServiceArea $sa;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sa = ServiceArea::create(['name' => 'PriceSA', 'slug' => 'price-sa']);
        $this->role = Role::create(['name' => 'PriceRole', 'slug' => 'price-role', 'level' => 1]);
        foreach (['pricing-baselines.create', 'pricing-baselines.update'] as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        }
    }

    public function test_index(): void
    {
        PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => '2025-01-01']);
        $user = $this->createUser('user');
        $this->getJson('/api/pricing-baselines', $this->authHeaders($user))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show(): void
    {
        $pb = PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 75, 'effective_from' => '2025-01-01']);
        $user = $this->createUser('user');
        $this->getJson("/api/pricing-baselines/{$pb->id}", $this->authHeaders($user))
            ->assertOk()->assertJsonFragment(['hourly_rate' => 75.0]);
    }

    public function test_store(): void
    {
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/pricing-baselines', [
            'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
            'hourly_rate' => 100, 'effective_from' => '2026-01-01',
        ], $this->authHeaders($staff))->assertStatus(201);
    }

    public function test_update(): void
    {
        $perm = Permission::firstOrCreate(['slug' => 'pricing-baselines.update']);
        RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $perm->id]);
        $pb = PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => '2025-01-01']);
        $staff = $this->createStaffWithProfile();
        $this->putJson("/api/pricing-baselines/{$pb->id}", ['hourly_rate' => 80], $this->authHeaders($staff))
            ->assertOk();
    }
}
