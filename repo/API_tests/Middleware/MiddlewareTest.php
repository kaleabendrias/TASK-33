<?php

namespace ApiTests\Middleware;

use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use App\Domain\Models\ServiceArea;
use App\Domain\Models\StaffProfile;
use ApiTests\TestCase;

class MiddlewareTest extends TestCase
{
    public function test_invalid_bearer_format(): void
    {
        $this->getJson('/api/auth/me', ['Authorization' => 'NotBearer xxx', 'Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_garbage_token(): void
    {
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer garbage.token.here', 'Accept' => 'application/json'])
            ->assertStatus(401);
    }

    public function test_missing_authorization_header(): void
    {
        $this->getJson('/api/service-areas', ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_role_middleware_user_vs_staff_endpoints(): void
    {
        $user = $this->createUser('user');
        // Service areas POST requires staff+
        $this->postJson('/api/service-areas', ['name' => 'Test'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_role_middleware_allows_higher_role(): void
    {
        $admin = $this->createUser('admin');
        ServiceArea::create(['name' => 'Pre', 'slug' => 'pre']);
        // Admin can access staff routes
        $this->postJson('/api/service-areas', ['name' => 'Admin Created'], $this->authHeaders($admin))
            ->assertStatus(201);
    }

    public function test_permission_middleware_denies_without_permission(): void
    {
        $staff = $this->createUser('staff');
        // No permissions seeded, so staff can't create service-areas
        $this->postJson('/api/service-areas', ['name' => 'Denied'], $this->authHeaders($staff))
            ->assertStatus(403);
    }

    public function test_permission_middleware_grants_with_permission(): void
    {
        $p = Permission::firstOrCreate(['slug' => 'service-areas.create']);
        RolePermission::firstOrCreate(['role' => 'staff', 'permission_id' => $p->id]);
        $staff = $this->createStaffWithProfile();
        $this->postJson('/api/service-areas', ['name' => 'Granted'], $this->authHeaders($staff))
            ->assertStatus(201);
    }

    public function test_disabled_user_rejected_after_auth(): void
    {
        $user = $this->createUser('admin', ['username' => 'disable_me']);
        $headers = $this->authHeaders($user);
        // Disable the user
        $user->update(['is_active' => false]);
        $this->getJson('/api/auth/me', $headers)->assertStatus(401);
    }
}
