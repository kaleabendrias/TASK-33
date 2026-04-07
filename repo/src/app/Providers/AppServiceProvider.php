<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

// Domain → Infrastructure bindings (dependency inversion)
use App\Domain\Contracts\ServiceAreaRepositoryInterface;
use App\Domain\Contracts\RoleRepositoryInterface;
use App\Domain\Contracts\ResourceRepositoryInterface;
use App\Domain\Contracts\PricingBaselineRepositoryInterface;
use App\Domain\Contracts\UserRepositoryInterface;
use App\Domain\Contracts\SessionRepositoryInterface;
use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Contracts\PermissionRepositoryInterface;

use App\Infrastructure\Repositories\EloquentServiceAreaRepository;
use App\Infrastructure\Repositories\EloquentRoleRepository;
use App\Infrastructure\Repositories\EloquentResourceRepository;
use App\Infrastructure\Repositories\EloquentPricingBaselineRepository;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Infrastructure\Repositories\EloquentSessionRepository;
use App\Infrastructure\Repositories\EloquentAuditLogRepository;
use App\Infrastructure\Repositories\EloquentPermissionRepository;

class AppServiceProvider extends ServiceProvider
{
    public array $bindings = [
        ServiceAreaRepositoryInterface::class      => EloquentServiceAreaRepository::class,
        RoleRepositoryInterface::class             => EloquentRoleRepository::class,
        ResourceRepositoryInterface::class         => EloquentResourceRepository::class,
        PricingBaselineRepositoryInterface::class   => EloquentPricingBaselineRepository::class,
        UserRepositoryInterface::class             => EloquentUserRepository::class,
        SessionRepositoryInterface::class          => EloquentSessionRepository::class,
        AuditLogRepositoryInterface::class         => EloquentAuditLogRepository::class,
        PermissionRepositoryInterface::class       => EloquentPermissionRepository::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(\App\Domain\Models\Order::class, \App\Domain\Policies\OrderPolicy::class);
    }
}
