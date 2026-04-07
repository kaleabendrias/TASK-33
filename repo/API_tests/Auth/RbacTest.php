<?php

namespace ApiTests\Auth;

use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use App\Domain\Models\ServiceArea;
use ApiTests\TestCase;

class RbacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceArea::create(['name' => 'RBAC Test', 'slug' => 'rbac-test']);
        // Create permissions
        $perms = ['service-areas.create', 'service-areas.update', 'resources.create', 'resources.update', 'resources.transition', 'pricing-baselines.create', 'pricing-baselines.update', 'roles.create', 'roles.update'];
        foreach ($perms as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug]);
            // Staff gets resource perms, GL gets all
            if (str_starts_with($slug, 'resources.') || str_starts_with($slug, 'pricing')) {
                RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
            }
            RolePermission::firstOrCreate(['role' => 'group-leader', 'permission_id' => $p->id]);
        }
    }

    // --- Role hierarchy ---

    public function test_user_can_read_service_areas(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/service-areas', $this->authHeaders($user))->assertOk();
    }

    public function test_user_cannot_write_service_areas(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/service-areas', ['name' => 'X'], $this->authHeaders($user))->assertStatus(403);
    }

    public function test_staff_cannot_create_service_area_without_permission(): void
    {
        $staff = $this->createUser('staff');
        $this->postJson('/api/service-areas', ['name' => 'X'], $this->authHeaders($staff))->assertStatus(403);
    }

    public function test_group_leader_can_create_service_area(): void
    {
        $gl = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/service-areas', ['name' => 'New Area'], $this->authHeaders($gl))->assertStatus(201);
    }

    public function test_admin_can_access_everything(): void
    {
        $admin = $this->createUser('admin');
        $h = $this->authHeaders($admin);
        $this->getJson('/api/admin/users', $h)->assertOk();
        $this->getJson('/api/admin/audit-logs', $h)->assertOk();
        $this->postJson('/api/service-areas', ['name' => 'Admin Area'], $h)->assertStatus(201);
    }

    // --- Permission-level checks ---

    public function test_staff_can_create_resource(): void
    {
        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = \App\Domain\Models\Role::create(['name' => 'Dev', 'slug' => 'dev-' . mt_rand(), 'level' => 1]);
        $this->postJson('/api/resources', [
            'name' => 'Staff Resource', 'service_area_id' => $sa->id, 'role_id' => $role->id,
        ], $this->authHeaders($staff))->assertStatus(201);
    }

    public function test_user_cannot_access_admin_routes(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/admin/users', $this->authHeaders($user))->assertStatus(403);
    }

    public function test_staff_cannot_access_admin_routes(): void
    {
        $staff = $this->createUser('staff');
        $this->getJson('/api/admin/users', $this->authHeaders($staff))->assertStatus(403);
    }

    public function test_group_leader_cannot_access_admin_routes(): void
    {
        $gl = $this->createUser('group-leader');
        $this->getJson('/api/admin/users', $this->authHeaders($gl))->assertStatus(403);
    }

    // --- Admin token revocation ---

    public function test_admin_can_revoke_user_tokens(): void
    {
        $admin = $this->createUser('admin');
        $target = $this->createUser('staff');
        // Create a session for target
        $this->postJson('/api/auth/login', ['username' => $target->username, 'password' => 'TestPass@12345!']);

        $this->postJson("/api/admin/users/{$target->id}/revoke-tokens", [], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure(['sessions_revoked']);
    }

    public function test_admin_can_reset_password(): void
    {
        $admin = $this->createUser('admin');
        $target = $this->createUser('staff');
        $this->postJson("/api/admin/users/{$target->id}/reset-password", [
            'password' => 'NewStrong@Pass99!',
        ], $this->authHeaders($admin))->assertOk();
    }

    public function test_admin_reset_password_weak_fails(): void
    {
        $admin = $this->createUser('admin');
        $target = $this->createUser('staff');
        $this->postJson("/api/admin/users/{$target->id}/reset-password", [
            'password' => 'weak',
        ], $this->authHeaders($admin))->assertStatus(422);
    }
}
