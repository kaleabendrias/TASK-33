<?php

namespace App\Domain\Contracts;

use App\Domain\Models\PricingBaseline;
use Illuminate\Database\Eloquent\Collection;

interface PricingBaselineRepositoryInterface
{
    public function all(): Collection;
    public function findOrFail(int $id): PricingBaseline;
    public function create(array $data): PricingBaseline;
    public function update(PricingBaseline $baseline, array $data): PricingBaseline;
    public function findActiveByServiceArea(int $serviceAreaId): Collection;
}
