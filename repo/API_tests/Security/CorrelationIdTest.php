<?php

namespace ApiTests\Security;

use ApiTests\TestCase;
use App\Api\Middleware\CorrelationId;
use Illuminate\Support\Facades\Log;

/**
 * End-to-end coverage for the per-request CorrelationId middleware.
 *
 * Three things are checked:
 *
 *   1. Every inbound request gets an X-Correlation-ID response header.
 *      The framework generates a UUIDv4 when the client did not
 *      supply one.
 *
 *   2. A well-formed upstream X-Correlation-ID is honoured. This
 *      lets a load balancer or upstream microservice propagate its
 *      own request ID through to our logs.
 *
 *   3. A malformed (or attacker-controlled) upstream value is
 *      rejected and replaced with a fresh UUID. Log injection via
 *      header content is a real attack class — newlines, control
 *      characters, and oversized values must never reach the log
 *      stream.
 *
 * The middleware also pushes the correlation ID into Laravel's
 * shared log context via Log::withContext(); we assert that
 * directly by inspecting the context after the request runs.
 */
class CorrelationIdTest extends TestCase
{
    public function test_response_carries_generated_correlation_id_header(): void
    {
        $resp = $this->getJson('/api/health');
        $resp->assertOk();

        $cid = $resp->headers->get(CorrelationId::HEADER);
        $this->assertNotEmpty($cid, 'Every response must carry an X-Correlation-ID header');
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_\-]{8,64}$/',
            $cid,
            'Generated correlation ID must be a safe, grepable identifier'
        );
    }

    public function test_well_formed_upstream_correlation_id_is_honoured(): void
    {
        $upstream = '3f7c2a99-1b2e-4e8f-9b06-04caaae9c2e0';
        $resp = $this->getJson('/api/health', [CorrelationId::HEADER => $upstream]);
        $resp->assertOk();
        $this->assertSame(
            $upstream,
            $resp->headers->get(CorrelationId::HEADER),
            'A well-formed upstream X-Correlation-ID must be propagated unchanged'
        );
    }

    public function test_malformed_upstream_correlation_id_is_replaced(): void
    {
        // Newline + arbitrary text — a textbook log-injection payload.
        // The middleware must reject it and substitute a fresh UUID
        // before any logging happens.
        $injected = "abc\nfake-log-line malicious";
        $resp = $this->getJson('/api/health', [CorrelationId::HEADER => $injected]);
        $resp->assertOk();

        $cid = $resp->headers->get(CorrelationId::HEADER);
        $this->assertNotSame(
            $injected,
            $cid,
            'Malformed correlation ID must be replaced, not echoed verbatim'
        );
        $this->assertStringNotContainsString(
            "\n",
            (string) $cid,
            'Replacement correlation ID must not contain newlines'
        );
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_\-]{8,64}$/',
            $cid,
        );
    }

    public function test_oversized_upstream_correlation_id_is_replaced(): void
    {
        // 65 chars — one over the cap. Anything longer is treated as
        // hostile or buggy and replaced.
        $oversized = str_repeat('a', 65);
        $resp = $this->getJson('/api/health', [CorrelationId::HEADER => $oversized]);
        $resp->assertOk();
        $this->assertNotSame($oversized, $resp->headers->get(CorrelationId::HEADER));
    }

    public function test_correlation_id_is_pushed_into_log_context(): void
    {
        // Drive a real request through the kernel so the middleware
        // runs end-to-end. After the request returns we inspect the
        // shared log context the framework keeps for downstream
        // Log::* calls — that is the exact context every channelized
        // log entry inherits via the global Log::withContext() call.
        $resp = $this->getJson('/api/health');
        $resp->assertOk();
        $cid = $resp->headers->get(CorrelationId::HEADER);

        $context = Log::sharedContext();
        $this->assertArrayHasKey(
            'correlation_id',
            $context,
            'CorrelationId middleware must push correlation_id into Log::withContext'
        );
        $this->assertSame(
            $cid,
            $context['correlation_id'],
            'The correlation_id in log context must match the X-Correlation-ID response header'
        );
    }
}
