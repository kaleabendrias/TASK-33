<?php

namespace ApiTests\Controllers;

use App\Domain\Models\Role;
use ApiTests\TestCase;

class RoleControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'TestRole', 'slug' => 'test-role', 'level' => 1]);
        // No permission seeding: foundational entity writes are
        // strictly admin-only.
    }

    public function test_index(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/roles', $this->authHeaders($user))->assertOk();
    }

    public function test_show(): void
    {
        $user = $this->createUser('user');
        $role = Role::first();
        $this->getJson("/api/roles/{$role->id}", $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'TestRole');
    }

    public function test_admin_can_store(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/roles', ['name' => 'NewRole'], $this->authHeaders($admin))
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug']]);
    }

    public function test_admin_can_update(): void
    {
        $admin = $this->createUser('admin');
        $role = Role::first();
        $this->putJson("/api/roles/{$role->id}", ['name' => 'Renamed'], $this->authHeaders($admin))
            ->assertOk()->assertJsonPath('data.name', 'Renamed');
    }

    public function test_staff_cannot_store(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson('/api/roles', ['name' => 'X'], $this->authHeaders($staff))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_group_leader_cannot_store(): void
    {
        $leader = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/roles', ['name' => 'X'], $this->authHeaders($leader))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_user_cannot_store(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/roles', ['name' => 'X'], $this->authHeaders($user))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_staff_cannot_update(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $role = Role::first();
        $this->putJson("/api/roles/{$role->id}", ['name' => 'X'], $this->authHeaders($staff))
            ->assertStatus(403)
            ->assertJsonStructure(['message']);
    }
}
