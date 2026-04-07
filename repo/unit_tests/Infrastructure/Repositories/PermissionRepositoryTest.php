<?php

namespace UnitTests\Infrastructure\Repositories;

use App\Domain\Models\Permission;
use App\Domain\Models\RolePermission;
use App\Infrastructure\Repositories\EloquentPermissionRepository;
use UnitTests\TestCase;

class PermissionRepositoryTest extends TestCase
{
    private EloquentPermissionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
        $this->repo = new EloquentPermissionRepository();
        $p = Permission::create(['slug' => 'test.action', 'description' => 'Test']);
        RolePermission::create(['role' => 'staff', 'permission_id' => $p->id]);
    }

    public function test_permissions_for_role(): void
    {
        $perms = $this->repo->permissionsForRole('staff');
        $this->assertTrue($perms->contains('test.action'));
    }

    public function test_role_has_permission(): void
    {
        $this->assertTrue($this->repo->roleHasPermission('staff', 'test.action'));
    }

    public function test_role_missing_permission(): void
    {
        $this->assertFalse($this->repo->roleHasPermission('user', 'test.action'));
    }

    public function test_admin_has_all_permissions(): void
    {
        $this->assertTrue($this->repo->roleHasPermission('admin', 'anything.at.all'));
        $this->assertTrue($this->repo->roleHasPermission('admin', 'test.action'));
    }
}
