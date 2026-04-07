<?php

namespace ApiTests\Controllers;

use App\Domain\Models\{Permission, Resource, Role, RolePermission, ServiceArea};
use ApiTests\TestCase;

class ResourceControllerTest extends TestCase
{
    private ServiceArea $sa;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sa = ServiceArea::create(['name' => 'RC SA', 'slug' => 'rc-sa']);
        $this->role = Role::create(['name' => 'RC Role', 'slug' => 'rc-role', 'level' => 1]);
        foreach (['resources.create', 'resources.update', 'resources.transition'] as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        }
    }

    public function test_index(): void
    {
        Resource::create(['name' => 'Idx', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $user = $this->createUser('user');
        $this->getJson('/api/resources', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'status']]]);
    }

    public function test_show(): void
    {
        $r = Resource::create(['name' => 'Show', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $user = $this->createUser('user');
        $this->getJson("/api/resources/{$r->id}", $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'Show');
    }

    public function test_store(): void
    {
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/resources', [
            'name' => 'NewR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
        ], $this->authHeaders($staff))->assertStatus(201);
    }

    public function test_update(): void
    {
        $r = Resource::create(['name' => 'Upd', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $staff = $this->createStaffWithProfile();
        $this->putJson("/api/resources/{$r->id}", ['name' => 'Updated'], $this->authHeaders($staff))
            ->assertOk()->assertJsonPath('data.name', 'Updated');
    }

    public function test_transition_valid(): void
    {
        $r = Resource::create(['name' => 'Trans', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $staff = $this->createStaffWithProfile();
        $this->postJson("/api/resources/{$r->id}/transition", ['status' => 'reserved'], $this->authHeaders($staff))
            ->assertOk();
    }

    public function test_transition_invalid(): void
    {
        $r = Resource::create(['name' => 'BadT', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $staff = $this->createStaffWithProfile();
        $this->postJson("/api/resources/{$r->id}/transition", ['status' => 'in_use'], $this->authHeaders($staff))
            ->assertStatus(422);
    }

    public function test_store_with_parent_id(): void
    {
        $parent = Resource::create(['name' => 'Parent', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/resources', [
            'name' => 'Child', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'parent_id' => $parent->id,
        ], $this->authHeaders($staff))->assertStatus(201)->assertJsonPath('data.parent_id', $parent->id);
    }
}
