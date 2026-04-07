<?php

namespace App\Application\Services;

use App\Domain\Contracts\RoleRepositoryInterface;
use App\Domain\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function get(int $id): Role
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): Role
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return $this->repository->create($data);
    }

    public function update(int $id, array $data): Role
    {
        $role = $this->repository->findOrFail($id);

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->repository->update($role, $data);
    }
}
