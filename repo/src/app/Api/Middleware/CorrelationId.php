<?php

namespace App\Api\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request correlation ID middleware.
 *
 * Generates a unique correlation ID for every inbound request and
 * pushes it into Laravel's log context via Log::withContext(). All
 * subsequent log entries emitted during this request — across the
 * security, business, errors, and stderr channels — automatically
 * carry the same `correlation_id` field, so an operator can trace
 * a single user action through every channel by grepping one value.
 *
 * The ID is also echoed back in the `X-Correlation-ID` response
 * header so a client (or an upstream proxy that needs to forward it
 * to another service) can include it in support tickets and bug
 * reports. If an upstream caller already supplied an
 * `X-Correlation-ID` header (e.g., a load balancer or a calling
 * microservice), we honour it — but only when it looks like a
 * well-formed identifier, to prevent log-injection via attacker
 * controlled values.
 */
class CorrelationId
{
    /**
     * The header name on both the request and the response.
     */
    public const HEADER = 'X-Correlation-ID';

    /**
     * Maximum length for an upstream-supplied correlation ID. Anything
     * longer is replaced with a freshly generated UUID — log lines
     * must remain compact and grepable, and oversized identifiers are
     * almost always either an injection attempt or a buggy client.
     */
    private const MAX_INBOUND_LENGTH = 64;

    /**
     * Allowed character set for an upstream-supplied correlation ID.
     * Letters, digits, dash, underscore — same shape as a UUID, a
     * Snowflake ID, or a typical request-id. Anything else (newlines,
     * control characters, JSON-control glyphs) is rejected because it
     * would corrupt log line parsing downstream.
     */
    private const SAFE_PATTERN = '/^[A-Za-z0-9_\-]+$/';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->resolveCorrelationId($request);

        // Stamp the request so any code path inside the application
        // can read it back without re-deriving it. Useful for jobs
        // that hand the ID off to a queue worker.
        $request->attributes->set('correlation_id', $correlationId);

        // Push into the SHARED log context. `shareContext()` (not
        // `withContext()`) is the load-bearing call here: it merges
        // the field into every currently-resolved channel AND seeds
        // every channel resolved later in the same request. So
        // Log::channel('security')->warning(...),
        // Log::channel('business')->info(...), and
        // Log::channel('errors')->error(...) all automatically carry
        // the same `correlation_id` even though none of them were
        // touched by name before this point. The framework clears
        // shared context between requests via the LogManager so
        // there is no bleed across concurrent or sequential calls.
        Log::shareContext(['correlation_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);

        // Echo back so clients can correlate their side of the wire
        // with our logs. Always set, even if an upstream proxy
        // already supplied one — this guarantees the response carries
        // the EXACT value we logged against, not a transformed copy.
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    /**
     * Reuse a well-formed upstream correlation ID if one was supplied;
     * otherwise generate a fresh UUIDv4. We never trust an upstream
     * value blindly — log injection via attacker-controlled headers
     * is a real attack class.
     */
    private function resolveCorrelationId(Request $request): string
    {
        $inbound = (string) $request->headers->get(self::HEADER, '');
        if ($inbound !== ''
            && strlen($inbound) <= self::MAX_INBOUND_LENGTH
            && preg_match(self::SAFE_PATTERN, $inbound) === 1) {
            return $inbound;
        }
        return (string) Str::uuid();
    }
}
