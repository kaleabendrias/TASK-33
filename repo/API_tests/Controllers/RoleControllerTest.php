<?php

namespace ApiTests\Controllers;

use App\Domain\Models\Permission;
use App\Domain\Models\Role;
use App\Domain\Models\RolePermission;
use ApiTests\TestCase;

class RoleControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'TestRole', 'slug' => 'test-role', 'level' => 1]);
        foreach (['roles.create', 'roles.update'] as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        }
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

    public function test_store(): void
    {
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/roles', ['name' => 'NewRole'], $this->authHeaders($staff))
            ->assertStatus(201);
    }

    public function test_update(): void
    {
        $staff = $this->createStaffWithProfile();
        $role = Role::first();
        $this->putJson("/api/roles/{$role->id}", ['name' => 'Renamed'], $this->authHeaders($staff))
            ->assertOk()->assertJsonPath('data.name', 'Renamed');
    }
}
