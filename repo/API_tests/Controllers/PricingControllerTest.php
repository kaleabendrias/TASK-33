<?php

namespace ApiTests\Controllers;

use App\Domain\Models\PricingBaseline;
use App\Domain\Models\Role;
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
        // No permission seeding: foundational entity writes are
        // strictly admin-only.
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

    public function test_admin_can_store(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/pricing-baselines', [
            'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
            'hourly_rate' => 100, 'effective_from' => '2026-01-01',
        ], $this->authHeaders($admin))
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'hourly_rate', 'effective_from']])
            ->assertJsonFragment(['effective_from' => '2026-01-01']);
    }

    public function test_admin_can_update(): void
    {
        $pb = PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => '2025-01-01']);
        $admin = $this->createUser('admin');
        $this->putJson("/api/pricing-baselines/{$pb->id}", ['hourly_rate' => 80], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonFragment(['hourly_rate' => 80.0]);
    }

    public function test_staff_cannot_store(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson('/api/pricing-baselines', [
            'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
            'hourly_rate' => 100, 'effective_from' => '2026-01-01',
        ], $this->authHeaders($staff))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_group_leader_cannot_store(): void
    {
        $leader = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/pricing-baselines', [
            'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
            'hourly_rate' => 100, 'effective_from' => '2026-01-01',
        ], $this->authHeaders($leader))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_staff_cannot_update(): void
    {
        $pb = PricingBaseline::create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => '2025-01-01']);
        $staff = $this->createStaffWithProfile('staff');
        $this->putJson("/api/pricing-baselines/{$pb->id}", ['hourly_rate' => 80], $this->authHeaders($staff))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }
}
