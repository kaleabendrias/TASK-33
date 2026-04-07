<?php

namespace UnitTests\Infrastructure\Repositories;

use App\Domain\Models\{PricingBaseline, Resource, Role, ServiceArea};
use App\Infrastructure\Repositories\{EloquentPricingBaselineRepository, EloquentResourceRepository, EloquentRoleRepository, EloquentServiceAreaRepository, EloquentSessionRepository};
use UnitTests\TestCase;

class CrudRepositoriesTest extends TestCase
{
    private ServiceArea $sa;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sa = ServiceArea::create(['name' => 'RepSA', 'slug' => 'rep-sa-' . mt_rand()]);
        $this->role = Role::create(['name' => 'RepRole', 'slug' => 'rep-role-' . mt_rand(), 'level' => 1]);
    }

    // ServiceArea Repository
    public function test_sa_repo_crud(): void
    {
        $repo = new EloquentServiceAreaRepository();
        $sa = $repo->create(['name' => 'NewSA', 'slug' => 'new-sa-' . mt_rand()]);
        $this->assertNotNull($sa->id);
        $found = $repo->findOrFail($sa->id);
        $this->assertEquals($sa->id, $found->id);
        $updated = $repo->update($sa, ['name' => 'UpdatedSA']);
        $this->assertEquals('UpdatedSA', $updated->name);
        $all = $repo->all();
        $this->assertGreaterThanOrEqual(1, $all->count());
    }

    // Role Repository
    public function test_role_repo_crud(): void
    {
        $repo = new EloquentRoleRepository();
        $r = $repo->create(['name' => 'NewRole', 'slug' => 'new-role-' . mt_rand(), 'level' => 3]);
        $found = $repo->findOrFail($r->id);
        $this->assertEquals($r->id, $found->id);
        $updated = $repo->update($r, ['name' => 'UpdatedRole']);
        $this->assertEquals('UpdatedRole', $updated->name);
        $this->assertGreaterThanOrEqual(1, $repo->all()->count());
    }

    // Resource Repository
    public function test_resource_repo_crud(): void
    {
        $repo = new EloquentResourceRepository();
        $r = $repo->create(['name' => 'CrudR', 'service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'capacity_hours' => 100]);
        $found = $repo->findOrFail($r->id);
        $this->assertEquals($r->id, $found->id);
        $updated = $repo->update($r, ['name' => 'CrudRUpd']);
        $this->assertEquals('CrudRUpd', $updated->name);
        $bySA = $repo->findByServiceArea($this->sa->id);
        $this->assertGreaterThanOrEqual(1, $bySA->count());
        $this->assertGreaterThanOrEqual(1, $repo->all()->count());
    }

    // PricingBaseline Repository
    public function test_pb_repo_crud(): void
    {
        $repo = new EloquentPricingBaselineRepository();
        $pb = $repo->create(['service_area_id' => $this->sa->id, 'role_id' => $this->role->id, 'hourly_rate' => 50, 'effective_from' => now()->subDay()]);
        $found = $repo->findOrFail($pb->id);
        $this->assertEquals($pb->id, $found->id);
        $updated = $repo->update($pb, ['hourly_rate' => 60]);
        $this->assertEquals('60.00', $updated->hourly_rate);
        $this->assertGreaterThanOrEqual(1, $repo->all()->count());
        $active = $repo->findActiveByServiceArea($this->sa->id);
        $this->assertGreaterThanOrEqual(1, $active->count());
    }

    // Session Repository — revokeOldestIfOverLimit
    public function test_session_repo_under_limit_no_revoke(): void
    {
        $user = \App\Domain\Models\User::create(['username' => 'sess_nolim', 'password' => 'TestPass@12345!', 'full_name' => 'S', 'role' => 'user']);
        $repo = new EloquentSessionRepository();
        $repo->createSession($user->id, bin2hex(random_bytes(32)), now()->addDay()->toDateTimeString());
        $repo->revokeOldestIfOverLimit($user->id, 5); // Limit is 5, only 1 session
        $active = $repo->activeSessions($user->id);
        $this->assertCount(1, $active); // No revocation
    }
}
