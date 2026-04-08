<?php

namespace ApiTests\Middleware;

use App\Domain\Models\ServiceArea;
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

    public function test_role_middleware_blocks_user_from_admin_writes(): void
    {
        $user = $this->createUser('user');
        // Service areas POST is admin-only.
        $this->postJson('/api/service-areas', ['name' => 'Test'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_role_middleware_admin_can_write_foundational_entities(): void
    {
        $admin = $this->createUser('admin');
        ServiceArea::create(['name' => 'Pre', 'slug' => 'pre']);
        $this->postJson('/api/service-areas', ['name' => 'Admin Created'], $this->authHeaders($admin))
            ->assertStatus(201);
    }

    public function test_role_middleware_blocks_staff_from_foundational_writes(): void
    {
        // Staff are blocked unconditionally — there is no permission row
        // that can unlock foundational entity writes for them.
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson('/api/service-areas', ['name' => 'Denied'], $this->authHeaders($staff))
            ->assertStatus(403);
    }

    public function test_role_middleware_blocks_group_leader_from_foundational_writes(): void
    {
        $leader = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/service-areas', ['name' => 'Denied'], $this->authHeaders($leader))
            ->assertStatus(403);
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
