<?php

namespace App\Api\Controllers;

use App\Api\Requests\PricingBaselineRequest;
use App\Api\Resources\PricingBaselineResource;
use App\Application\Services\PricingBaselineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PricingBaselineController extends Controller
{
    public function __construct(
        private readonly PricingBaselineService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return PricingBaselineResource::collection($this->service->list());
    }

    public function show(int $id): PricingBaselineResource
    {
        return new PricingBaselineResource($this->service->get($id));
    }

    public function store(PricingBaselineRequest $request): JsonResponse
    {
        $baseline = $this->service->create($request->validated());

        return (new PricingBaselineResource($baseline))
            ->additional(['message' => 'Pricing baseline created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(PricingBaselineRequest $request, int $id): PricingBaselineResource
    {
        return new PricingBaselineResource($this->service->update($id, $request->validated()));
    }
}
