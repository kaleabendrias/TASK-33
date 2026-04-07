<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\PermissionRepositoryInterface;
use App\Domain\Models\RolePermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentPermissionRepository implements PermissionRepositoryInterface
{
    public function permissionsForRole(string $role): Collection
    {
        return Cache::remember("permissions:{$role}", 300, function () use ($role) {
            return RolePermission::where('role', $role)
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->pluck('permissions.slug');
        });
    }

    public function roleHasPermission(string $role, string $permissionSlug): bool
    {
        // Admin has all permissions implicitly
        if ($role === 'admin') {
            return true;
        }

        return $this->permissionsForRole($role)->contains($permissionSlug);
    }
}
