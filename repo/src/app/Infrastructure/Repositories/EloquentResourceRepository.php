<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\ResourceRepositoryInterface;
use App\Domain\Models\Resource;
use Illuminate\Database\Eloquent\Collection;

class EloquentResourceRepository implements ResourceRepositoryInterface
{
    public function all(): Collection
    {
        return Resource::with(['serviceArea', 'role'])->orderBy('name')->get();
    }

    public function findOrFail(int $id): Resource
    {
        return Resource::with(['serviceArea', 'role'])->findOrFail($id);
    }

    public function create(array $data): Resource
    {
        return Resource::create($data)->load(['serviceArea', 'role']);
    }

    public function update(Resource $resource, array $data): Resource
    {
        $resource->update($data);
        return $resource->refresh()->load(['serviceArea', 'role']);
    }

    public function findByServiceArea(int $serviceAreaId): Collection
    {
        return Resource::with(['role'])
            ->where('service_area_id', $serviceAreaId)
            ->orderBy('name')
            ->get();
    }
}
