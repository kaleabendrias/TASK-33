<?php

namespace App\Api\Controllers;

use App\Application\Services\AuthService;
use App\Application\Services\UserService;
use App\Api\Requests\CreateUserRequest;
use App\Api\Requests\AdminPasswordResetRequest;
use App\Api\Resources\UserResource;
use App\Domain\Contracts\AuditLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserService $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    // ── User management ─────────────────────────────────────────────

    public function listUsers(): AnonymousResourceCollection
    {
        return UserResource::collection($this->users->list());
    }

    public function showUser(int $id): UserResource
    {
        return new UserResource($this->users->get($id));
    }

    public function createUser(CreateUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'User created.'])
            ->response()
            ->setStatusCode(201);
    }

    // ── Token revocation ────────────────────────────────────────────

    public function revokeUserTokens(int $userId): JsonResponse
    {
        $count = $this->auth->adminRevokeTokens($userId);

        return response()->json([
            'message'          => 'All sessions revoked.',
            'sessions_revoked' => $count,
        ]);
    }

    // ── Offline password reset ─────��────────────────────────────────

    public function resetPassword(AdminPasswordResetRequest $request, int $userId): JsonResponse
    {
        $this->auth->adminResetPassword($userId, $request->validated('password'));

        return response()->json([
            'message' => 'Password reset. All existing sessions have been revoked.',
        ]);
    }

    // ── Audit logs ──────────────────────────────────────────────────

    public function auditLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['action', 'entity_type', 'entity_id', 'actor_id', 'from', 'to']);
        $perPage = min((int) $request->query('per_page', 25), 100);

        $logs = $this->auditLogs->paginate($filters, $perPage);

        return response()->json($logs);
    }
}
