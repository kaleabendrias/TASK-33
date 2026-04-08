<?php

use Illuminate\Support\Facades\Route;
use App\Api\Controllers\AuthController;
use App\Api\Controllers\AdminController;
use App\Api\Controllers\ServiceAreaController;
use App\Api\Controllers\RoleController;
use App\Api\Controllers\ResourceController;
use App\Api\Controllers\PricingBaselineController;
use App\Api\Controllers\PricingRuleController;
use App\Api\Controllers\BookingApiController;
use App\Api\Controllers\OrderApiController;
use App\Api\Controllers\SettlementApiController;
use App\Api\Controllers\ExportApiController;
use App\Api\Controllers\AttachmentController;
use App\Api\Controllers\StaffProfileApiController;
use App\Api\Controllers\DashboardApiController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'timestamp' => now()->toIso8601String(),
]));

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (JWT required)
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->group(function () {

    // Auth lifecycle
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Staff profile (self-service)
    Route::get('/profile', [StaffProfileApiController::class, 'show']);
    Route::put('/profile', [StaffProfileApiController::class, 'update']);

    // Dashboard summary (any authenticated caller; service applies role gating)
    Route::get('/dashboard/stats', [DashboardApiController::class, 'stats']);

    // ── Read endpoints (any authenticated user) ─────────────────────

    Route::apiResource('service-areas', ServiceAreaController::class)->only(['index', 'show']);
    Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
    Route::apiResource('resources', ResourceController::class)->only(['index', 'show']);
    Route::apiResource('pricing-baselines', PricingBaselineController::class)->only(['index', 'show']);

    // Booking catalog, availability, pricing (any authenticated user)
    Route::get('/bookings/items', [BookingApiController::class, 'items']);
    Route::post('/bookings/check-availability', [BookingApiController::class, 'checkAvailability']);
    Route::post('/bookings/calculate-totals', [BookingApiController::class, 'calculateTotals']);
    Route::post('/bookings/validate-coupon', [BookingApiController::class, 'validateCoupon']);

    // Orders — any authenticated user can create, view own, and manage own
    Route::get('/orders', [OrderApiController::class, 'index']);
    Route::get('/orders/{id}', [OrderApiController::class, 'show']);
    Route::post('/orders', [OrderApiController::class, 'store']);
    Route::post('/orders/{id}/transition', [OrderApiController::class, 'transition']);
    Route::post('/orders/{id}/refund', [OrderApiController::class, 'refund']);
    Route::post('/orders/{id}/mark-unavailable', [OrderApiController::class, 'markUnavailable']);

    // Attachment download (authorized in controller)
    Route::get('/attachments/{id}/download', [AttachmentController::class, 'download']);

    // Exports (scoped by user in controller)
    Route::post('/exports', [ExportApiController::class, 'export']);

    // ── Staff+ operational write surface ────────────────────────────
    // Limited to operational concerns (attachments). Foundational
    // entity writes are admin-only — see the admin block below.
    Route::middleware(['role:staff', 'profile.complete'])->group(function () {
        // Attachment upload
        Route::post('/attachments', [AttachmentController::class, 'upload']);
    });

    // ── Settlement read access — staff+ (row-level scoped) ─────────
    //
    // Read access to financial summaries is gated by ROLE only — the
    // profile.complete middleware is reserved for *operational* actions
    // (check-in / check-out / approvals). A staff member with an
    // incomplete profile must still be able to inspect their own
    // settlement summary; SettlementService applies strict row-level
    // scoping so cross-tenant data never leaks regardless of profile state.
    Route::middleware('role:staff')->group(function () {
        Route::get('/settlements', [SettlementApiController::class, 'index']);
        Route::get('/settlements/{id}', [SettlementApiController::class, 'show']);
        Route::get('/commissions', [SettlementApiController::class, 'commissions']);
        Route::get('/commissions/attributed-orders', [SettlementApiController::class, 'attributedOrders']);
    });

    // ── Admin-only ──────────────────────────────────────────────────

    // ── Foundational entity writes (admin-only, no /admin prefix) ───
    // These mutate billing- and operations-critical baselines and must
    // never be available to staff or group-leaders, per requirements.
    //
    // Each route also carries a `permission:*` slug. The role:admin
    // gate is the hard role boundary; the permission slug is the
    // feature-level identifier the UI introspects so individual
    // buttons can be conditionally rendered (see GET /auth/me's
    // `effective_permissions` field). Admin holds every permission
    // implicitly (see EloquentPermissionRepository::roleHasPermission)
    // so this composes cleanly without per-admin seeding.
    Route::middleware('role:admin')->group(function () {
        Route::post('/service-areas', [ServiceAreaController::class, 'store'])
            ->middleware('permission:service-areas.create');
        Route::put('/service-areas/{service_area}', [ServiceAreaController::class, 'update'])
            ->middleware('permission:service-areas.update');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.update');
        Route::post('/resources', [ResourceController::class, 'store'])
            ->middleware('permission:resources.create');
        Route::put('/resources/{resource}', [ResourceController::class, 'update'])
            ->middleware('permission:resources.update');
        Route::post('/resources/{resource}/transition', [ResourceController::class, 'transition'])
            ->middleware('permission:resources.transition');
        Route::post('/pricing-baselines', [PricingBaselineController::class, 'store'])
            ->middleware('permission:pricing-baselines.create');
        Route::put('/pricing-baselines/{pricing_baseline}', [PricingBaselineController::class, 'update'])
            ->middleware('permission:pricing-baselines.update');
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'listUsers']);
        Route::get('/users/{id}', [AdminController::class, 'showUser']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::post('/users/{id}/revoke-tokens', [AdminController::class, 'revokeUserTokens']);
        Route::post('/users/{id}/reset-password', [AdminController::class, 'resetPassword']);
        Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
        Route::post('/settlements/generate', [SettlementApiController::class, 'generate']);
        Route::post('/settlements/{id}/finalize', [SettlementApiController::class, 'finalize']);

        // Multi-dimensional pricing rule catalog (admin only)
        Route::get('/pricing-rules', [PricingRuleController::class, 'index']);
        Route::get('/pricing-rules/{id}', [PricingRuleController::class, 'show']);
        Route::post('/pricing-rules', [PricingRuleController::class, 'store']);
        Route::put('/pricing-rules/{id}', [PricingRuleController::class, 'update']);
        Route::delete('/pricing-rules/{id}', [PricingRuleController::class, 'destroy']);
    });
});
