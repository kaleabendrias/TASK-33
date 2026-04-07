<?php

namespace App\Api\Controllers;

use App\Api\Requests\ResourceRequest;
use App\Api\Resources\ResourceResource;
use App\Application\Services\ResourceService;
use App\Domain\Contracts\AuditLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ResourceController extends Controller
{
    public function __construct(
        private readonly ResourceService $service,
        private readonly AuditLogRepositoryInterface $audit,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ResourceResource::collection($this->service->list());
    }

    public function show(int $id): ResourceResource
    {
        return new ResourceResource($this->service->get($id));
    }

    public function store(ResourceRequest $request): JsonResponse
    {
        $resource = $this->service->create($request->validated());

        return (new ResourceResource($resource))
            ->additional(['message' => 'Resource created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(ResourceRequest $request, int $id): ResourceResource
    {
        return new ResourceResource($this->service->update($id, $request->validated()));
    }

    public function transition(Request $request, int $resource): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:available,reserved,in_use,maintenance,decommissioned',
            'reason' => 'sometimes|nullable|string|max:500',
        ]);

        $model = $this->service->get($resource);

        try {
            $model->transitionTo(
                $request->input('status'),
                $request->input('reason'),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log('resource_transition', 'Resource', $model->id, null, [
            'status' => $request->input('status'),
            'reason' => $request->input('reason'),
        ]);

        return response()->json([
            'data'    => new ResourceResource($model->refresh()),
            'message' => 'Status transitioned.',
        ]);
    }
}
