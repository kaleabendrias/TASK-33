<?php

namespace App\Domain\Contracts;

use App\Domain\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleRepositoryInterface
{
    public function all(): Collection;
    public function findOrFail(int $id): Role;
    public function create(array $data): Role;
    public function update(Role $role, array $data): Role;
}
