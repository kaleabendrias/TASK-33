<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth'         => \App\Api\Middleware\JwtAuthenticate::class,
            'role'             => \App\Api\Middleware\RequireRole::class,
            'permission'       => \App\Api\Middleware\RequirePermission::class,
            'web.auth'         => \App\Api\Middleware\WebSessionAuth::class,
            'profile.complete' => \App\Api\Middleware\RequireProfileComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Channelized error reporting. All major application
        // exceptions are routed through the dedicated 'errors'
        // channel with full stack-trace context, so the operations
        // dashboard sees a single, structured stream — no more
        // grepping the default stderr firehose for "what just broke".
        //
        // Quiet, expected exceptions (404s, 401s, 403s, validation
        // failures) are suppressed from this stream because they are
        // not actionable errors and would otherwise drown out the
        // real signals. They remain visible to clients via the
        // normal HTTP response.
        $exceptions->reportable(function (\Throwable $e) {
            if ($e instanceof ValidationException
                || $e instanceof ModelNotFoundException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500)) {
                return false;
            }

            Log::channel('errors')->error($e->getMessage(), [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
                'previous'  => $e->getPrevious()?->getMessage(),
            ]);

            // Returning false stops Laravel's default reporter so the
            // error is not double-logged into the stack channel.
            return false;
        });

        // Critical validation failures (5xx-class user errors that
        // bubble all the way up — e.g., a domain invariant being
        // violated by an internal caller, not a user input mistake)
        // get the full errors-channel treatment too.
        $exceptions->reportable(function (\InvalidArgumentException $e) {
            Log::channel('errors')->error('domain.invariant_violation: '.$e->getMessage(), [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return false;
        });
    })
    ->create();
