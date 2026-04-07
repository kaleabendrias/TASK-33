<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\ServiceAreaRepositoryInterface;
use App\Domain\Models\ServiceArea;
use Illuminate\Database\Eloquent\Collection;

class EloquentServiceAreaRepository implements ServiceAreaRepositoryInterface
{
    public function all(): Collection
    {
        return ServiceArea::orderBy('name')->get();
    }

    public function findOrFail(int $id): ServiceArea
    {
        return ServiceArea::findOrFail($id);
    }

    public function create(array $data): ServiceArea
    {
        return ServiceArea::create($data);
    }

    public function update(ServiceArea $serviceArea, array $data): ServiceArea
    {
        $serviceArea->update($data);
        return $serviceArea->refresh();
    }
}
