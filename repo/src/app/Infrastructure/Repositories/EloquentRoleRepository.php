<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\RoleRepositoryInterface;
use App\Domain\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::orderBy('level')->get();
    }

    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);
        return $role->refresh();
    }
}
