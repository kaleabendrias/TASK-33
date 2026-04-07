<?php

namespace App\Application\Services;

use App\Domain\Contracts\ServiceAreaRepositoryInterface;
use App\Domain\Models\ServiceArea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ServiceAreaService
{
    public function __construct(
        private readonly ServiceAreaRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function get(int $id): ServiceArea
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): ServiceArea
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return $this->repository->create($data);
    }

    public function update(int $id, array $data): ServiceArea
    {
        $serviceArea = $this->repository->findOrFail($id);

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->repository->update($serviceArea, $data);
    }
}
