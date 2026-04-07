<?php

namespace App\Api\Controllers;

use App\Api\Requests\RoleRequest;
use App\Api\Resources\RoleResource;
use App\Application\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->service->list());
    }

    public function show(int $id): RoleResource
    {
        return new RoleResource($this->service->get($id));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = $this->service->create($request->validated());

        return (new RoleResource($role))
            ->additional(['message' => 'Role created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(RoleRequest $request, int $id): RoleResource
    {
        return new RoleResource($this->service->update($id, $request->validated()));
    }
}
