<?php

namespace App\Application\Services;

use App\Domain\Contracts\ResourceRepositoryInterface;
use App\Domain\Models\Resource;
use App\Domain\Policies\ResourcePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ResourceService
{
    public function __construct(
        private readonly ResourceRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function get(int $id): Resource
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): Resource
    {
        if (isset($data['capacity_hours']) && !ResourcePolicy::isWithinCapacityLimit((float) $data['capacity_hours'])) {
            throw ValidationException::withMessages([
                'capacity_hours' => "Capacity must be between 0 and " . ResourcePolicy::MAX_CAPACITY_HOURS . " hours.",
            ]);
        }

        return $this->repository->create($data);
    }

    public function update(int $id, array $data): Resource
    {
        $resource = $this->repository->findOrFail($id);

        if (isset($data['capacity_hours']) && !ResourcePolicy::isWithinCapacityLimit((float) $data['capacity_hours'])) {
            throw ValidationException::withMessages([
                'capacity_hours' => "Capacity must be between 0 and " . ResourcePolicy::MAX_CAPACITY_HOURS . " hours.",
            ]);
        }

        return $this->repository->update($resource, $data);
    }
}
