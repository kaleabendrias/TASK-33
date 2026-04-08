<?php

namespace ApiTests\Controllers;

use App\Domain\Models\ServiceArea;
use ApiTests\TestCase;

class ServiceAreaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ServiceArea::create(['name' => 'SA1', 'slug' => 'sa1']);
        // No permission seeding: foundational entity writes are now
        // strictly admin-only and cannot be unlocked via the rolewise
        // permission table.
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

    public function test_admin_can_store(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/service-areas', ['name' => 'New SA'], $this->authHeaders($admin))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'new-sa');
    }

    public function test_store_validation(): void
    {
        $admin = $this->createUser('admin');
        $this->postJson('/api/service-areas', [], $this->authHeaders($admin))
            ->assertStatus(422);
    }

    public function test_admin_can_update(): void
    {
        $admin = $this->createUser('admin');
        $sa = ServiceArea::first();
        $this->putJson("/api/service-areas/{$sa->id}", ['name' => 'Updated'], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated');
    }

    public function test_staff_cannot_store(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $this->postJson('/api/service-areas', ['name' => 'Forbidden'], $this->authHeaders($staff))
            ->assertStatus(403);
    }

    public function test_group_leader_cannot_store(): void
    {
        $leader = $this->createStaffWithProfile('group-leader');
        $this->postJson('/api/service-areas', ['name' => 'Forbidden'], $this->authHeaders($leader))
            ->assertStatus(403);
    }

    public function test_user_cannot_store(): void
    {
        $user = $this->createUser('user');
        $this->postJson('/api/service-areas', ['name' => 'Forbidden'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_staff_cannot_update(): void
    {
        $staff = $this->createStaffWithProfile('staff');
        $sa = ServiceArea::first();
        $this->putJson("/api/service-areas/{$sa->id}", ['name' => 'X'], $this->authHeaders($staff))
            ->assertStatus(403);
    }
}
