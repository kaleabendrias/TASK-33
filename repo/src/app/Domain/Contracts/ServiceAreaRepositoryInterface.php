<?php

namespace App\Domain\Contracts;

use App\Domain\Models\ServiceArea;
use Illuminate\Database\Eloquent\Collection;

interface ServiceAreaRepositoryInterface
{
    public function all(): Collection;
    public function findOrFail(int $id): ServiceArea;
    public function create(array $data): ServiceArea;
    public function update(ServiceArea $serviceArea, array $data): ServiceArea;
}
