<?php

namespace ApiTests\Controllers;

use App\Domain\Models\{Resource, Role, ServiceArea};
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
        // No permission seeding: foundational entity writes are
        // strictly admin-only.
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

    public function test_admin_can_store(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/resources', [
            'name' => 'NewR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
        ], $this->authHeaders($admin))
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'status']])
            ->assertJsonPath('data.name', 'NewR');
    }

    public function test_admin_can_update(): void
    {
        $r = Resource::create(['name' => 'Upd', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $admin = $this->createUser('admin');
        $this->putJson("/api/resources/{$r->id}", ['name' => 'Updated'], $this->authHeaders($admin))
            ->assertOk()->assertJsonPath('data.name', 'Updated');
    }

    public function test_admin_transition_valid(): void
    {
        $r = Resource::create(['name' => 'Trans', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $admin = $this->createUser('admin');
        $this->postJson("/api/resources/{$r->id}/transition", ['status' => 'reserved'], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved');
    }

    public function test_admin_transition_invalid(): void
    {
        $r = Resource::create(['name' => 'BadT', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $admin = $this->createUser('admin');
        $this->postJson("/api/resources/{$r->id}/transition", ['status' => 'in_use'], $this->authHeaders($admin))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_admin_store_with_parent_id(): void
    {
        $parent = Resource::create(['name' => 'Parent', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10]);
        $admin = $this->createUser('admin');
        $this->postJson('/api/resources', [
            'name' => 'Child', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'parent_id' => $parent->id,
        ], $this->authHeaders($admin))->assertStatus(201)->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_staff_cannot_store(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson('/api/resources', [
            'name' => 'X', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
        ], $this->authHeaders($staff))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_group_leader_cannot_store(): void
    {
        $leader = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/resources', [
            'name' => 'X', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id,
        ], $this->authHeaders($leader))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_staff_cannot_transition(): void
    {
        $r = Resource::create(['name' => 'X', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 10, 'status' => 'available']);
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson("/api/resources/{$r->id}/transition", ['status' => 'reserved'], $this->authHeaders($staff))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }
}
