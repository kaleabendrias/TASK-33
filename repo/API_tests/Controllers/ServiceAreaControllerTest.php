<?php

namespace ApiTests\Controllers;

use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use App\Domain\Models\ServiceArea;
use ApiTests\TestCase;

class ServiceAreaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceArea::create(['name' => 'SA1', 'slug' => 'sa1']);
        foreach (['service-areas.create', 'service-areas.update'] as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        }
    }

    public function test_index(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/service-areas', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }

    public function test_show(): void
    {
        $user = $this->createUser('user');
        $sa = ServiceArea::first();
        $this->getJson("/api/service-areas/{$sa->id}", $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'SA1');
    }

    public function test_store(): void
    {
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/service-areas', ['name' => 'New SA'], $this->authHeaders($staff))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'new-sa');
    }

    public function test_store_validation(): void
    {
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/service-areas', [], $this->authHeaders($staff))
            ->assertStatus(422);
    }

    public function test_update(): void
    {
        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $this->putJson("/api/service-areas/{$sa->id}", ['name' => 'Updated'], $this->authHeaders($staff))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated');
    }
}
