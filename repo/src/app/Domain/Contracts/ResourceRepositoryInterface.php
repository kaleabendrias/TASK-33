<?php

namespace App\Domain\Contracts;

use App\Domain\Models\Resource;
use Illuminate\Database\Eloquent\Collection;

interface ResourceRepositoryInterface
{
    public function all(): Collection;
    public function findOrFail(int $id): Resource;
    public function create(array $data): Resource;
    public function update(Resource $resource, array $data): Resource;
    public function findByServiceArea(int $serviceAreaId): Collection;
}
