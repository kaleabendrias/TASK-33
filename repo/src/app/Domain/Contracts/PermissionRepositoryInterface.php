<?php

namespace App\Domain\Contracts;

use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    /** Get all permission slugs assigned to a role. */
    public function permissionsForRole(string $role): Collection;

    /** Check if a specific role has a specific permission. */
    public function roleHasPermission(string $role, string $permissionSlug): bool;
}
