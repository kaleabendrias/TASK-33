<?php

namespace App\Api\Controllers;

use App\Api\Requests\ServiceAreaRequest;
use App\Api\Resources\ServiceAreaResource;
use App\Application\Services\ServiceAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ServiceAreaController extends Controller
{
    public function __construct(
        private readonly ServiceAreaService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ServiceAreaResource::collection($this->service->list());
    }

    public function show(int $id): ServiceAreaResource
    {
        return new ServiceAreaResource($this->service->get($id));
    }

    public function store(ServiceAreaRequest $request): JsonResponse
    {
        $serviceArea = $this->service->create($request->validated());

        return (new ServiceAreaResource($serviceArea))
            ->additional(['message' => 'Service area created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(ServiceAreaRequest $request, int $id): ServiceAreaResource
    {
        return new ServiceAreaResource($this->service->update($id, $request->validated()));
    }
}
