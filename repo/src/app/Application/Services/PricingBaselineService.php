<?php

namespace App\Application\Services;

use App\Domain\Contracts\PricingBaselineRepositoryInterface;
use App\Domain\Models\PricingBaseline;
use App\Domain\Policies\PricingPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PricingBaselineService
{
    public function __construct(
        private readonly PricingBaselineRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function get(int $id): PricingBaseline
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data): PricingBaseline
    {
        $this->validateRate($data);

        return $this->repository->create($data);
    }

    public function update(int $id, array $data): PricingBaseline
    {
        $baseline = $this->repository->findOrFail($id);

        if (isset($data['hourly_rate'])) {
            $this->validateRate($data);
        }

        return $this->repository->update($baseline, $data);
    }

    private function validateRate(array $data): void
    {
        if (isset($data['hourly_rate']) && !PricingPolicy::meetsMinimumRate((float) $data['hourly_rate'])) {
            throw ValidationException::withMessages([
                'hourly_rate' => 'Hourly rate must be at least $10.00.',
            ]);
        }
    }
}
