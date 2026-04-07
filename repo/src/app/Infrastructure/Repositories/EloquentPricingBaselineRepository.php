<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\PricingBaselineRepositoryInterface;
use App\Domain\Models\PricingBaseline;
use Illuminate\Database\Eloquent\Collection;

class EloquentPricingBaselineRepository implements PricingBaselineRepositoryInterface
{
    public function all(): Collection
    {
        return PricingBaseline::with(['serviceArea', 'role'])->orderBy('effective_from')->get();
    }

    public function findOrFail(int $id): PricingBaseline
    {
        return PricingBaseline::with(['serviceArea', 'role'])->findOrFail($id);
    }

    public function create(array $data): PricingBaseline
    {
        return PricingBaseline::create($data)->load(['serviceArea', 'role']);
    }

    public function update(PricingBaseline $baseline, array $data): PricingBaseline
    {
        $baseline->update($data);
        return $baseline->refresh()->load(['serviceArea', 'role']);
    }

    public function findActiveByServiceArea(int $serviceAreaId): Collection
    {
        return PricingBaseline::with(['role'])
            ->where('service_area_id', $serviceAreaId)
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', now());
            })
            ->where('effective_from', '<=', now())
            ->get();
    }
}
