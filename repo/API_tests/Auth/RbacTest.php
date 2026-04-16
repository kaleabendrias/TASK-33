<?php

namespace ApiTests\Auth;

use App\Domain\Models\ServiceArea;
use ApiTests\TestCase;

class RbacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceArea::create(['name' => 'RBAC Test', 'slug' => 'rbac-test']);
        // No permission seeding: foundational entity writes are
        // strictly admin-only and cannot be unlocked via the rolewise
        // permission table.
    }

    // --- Role hierarchy ---

    public function test_user_can_read_service_areas(): void
    {
        $user = $this->createUser('user');
        $this->getJson('/api/service-areas', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }

    public function test_user_cannot_write_service_areas(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/service-areas', ['name' => 'X'], $this->authHeaders($user))->assertStatus(403);
    }

    public function test_staff_cannot_create_service_area(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson('/api/service-areas', ['name' => 'X'], $this->authHeaders($staff))->assertStatus(403);
    }

    public function test_group_leader_cannot_create_service_area(): void
    {
        // Foundational entity writes are admin-only — group leaders are
        // explicitly forbidden, regardless of profile completeness.
        $gl = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/service-areas', ['name' => 'New Area'], $this->authHeaders($gl))->assertStatus(403);
    }

    public function test_admin_can_access_everything(): void
    {
        $admin = $this->createUser('admin');
        $h = $this->authHeaders($admin);
        $this->getJson('/api/admin/users', $h)
            ->assertOk()
            ->assertJsonStructure(['data']);
        $this->getJson('/api/admin/audit-logs', $h)
            ->assertOk()
            ->assertJsonStructure(['data']);
        $this->postJson('/api/service-areas', ['name' => 'Admin Area'], $h)
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'admin-area');
    }

    // --- Permission-level checks ---

    public function test_staff_cannot_create_resource(): void
    {
        // Resource creation is a foundational write — admin-only.
        $staff = $this->createStaffWithProfile();
        $sa = ServiceArea::first();
        $role = \App\Domain\Models\Role::create(['name' => 'Dev', 'slug' => 'dev-' . mt_rand(), 'level' => 1]);
        $this->postJson('/api/resources', [
            'name' => 'Staff Resource', 'service_area_id' => $sa->id, 'role_id' => $role->id,
        ], $this->authHeaders($staff))->assertStatus(403);
    }

    public function test_admin_can_create_resource(): void
    {
        $admin = $this->createUser('admin');
        $sa = ServiceArea::first();
        $role = \App\Domain\Models\Role::create(['name' => 'AdminDev', 'slug' => 'admin-dev-' . mt_rand(), 'level' => 1]);
        $this->postJson('/api/resources', [
            'name' => 'Admin Resource', 'service_area_id' => $sa->id, 'role_id' => $role->id,
        ], $this->authHeaders($admin))->assertStatus(201);
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
