<?php

namespace ApiTests\Security;

use ApiTests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Regression coverage for the Zero-Config-File security model.
 *
 * Three layers are exercised:
 *
 *   1. Static guarantee — the docker-compose.yml MUST NOT contain the
 *      historical `admin@local.dev` / `admin` defaults that the
 *      Partial Fix relied on. If anyone re-introduces them in a
 *      refactor, this test fails before the change ships.
 *
 *   2. Interpolation guarantee — the compose file MUST use the
 *      mandatory `${VAR:?…}` form so a missing variable aborts
 *      `docker compose up` with a hard error rather than silently
 *      falling back to a weak default.
 *
 *   3. Live HTTP guarantee — when the test suite runs inside the
 *      `app` container, the pgAdmin service is reachable on the
 *      `pgadmin:80` compose-network address. We POST the historical
 *      defaults to its login form and assert the resulting session
 *      cookie does NOT grant access to the authenticated browser
 *      view. Any successful login with the legacy credentials would
 *      mean the hardening regressed.
 */
class EnvironmentHardeningTest extends TestCase
{
    /**
     * The compose file is bind-mounted read-only into the app container
     * by docker-compose.yml itself so this regression check can read it
     * without leaving the test runtime.
     */
    private const COMPOSE_PATH = '/var/www/docker-compose.yml';

    public function test_compose_file_does_not_contain_legacy_pgadmin_defaults(): void
    {
        if (!is_readable(self::COMPOSE_PATH)) {
            $this->markTestSkipped('docker-compose.yml not bind-mounted into this test runtime');
        }
        $compose = file_get_contents(self::COMPOSE_PATH);
        $this->assertNotFalse($compose, 'docker-compose.yml must be readable from the test container');

        // Strip the comment block that documents the security model so
        // a literal mention of the historical defaults inside an
        // explanatory comment cannot trip the regression check.
        $stripped = preg_replace('/^\s*#.*$/m', '', $compose);

        $this->assertStringNotContainsString(
            'admin@local.dev',
            $stripped,
            'Legacy pgAdmin email default must not appear outside comments — Zero-Config-File model has regressed'
        );
        $this->assertStringNotContainsString(
            ':-admin}',
            $stripped,
            'Legacy `${VAR:-admin}` weak fallback must not exist anywhere in docker-compose.yml'
        );
    }

    public function test_compose_file_requires_mandatory_pgadmin_credentials(): void
    {
        if (!is_readable(self::COMPOSE_PATH)) {
            $this->markTestSkipped('docker-compose.yml not bind-mounted into this test runtime');
        }
        $compose = file_get_contents(self::COMPOSE_PATH);
        $this->assertNotFalse($compose);

        // The mandatory `${VAR:?…}` form is the load-bearing piece of
        // the Zero-Config-File model: it makes docker compose abort
        // when the credential is unset, so a partial secret injection
        // cannot silently fall back to a default.
        $this->assertMatchesRegularExpression(
            '/PGADMIN_DEFAULT_EMAIL:\s*"\$\{PGADMIN_DEFAULT_EMAIL:\?[^}]+\}"/',
            $compose,
            'PGADMIN_DEFAULT_EMAIL must be required via `${VAR:?…}` interpolation'
        );
        $this->assertMatchesRegularExpression(
            '/PGADMIN_DEFAULT_PASSWORD:\s*"\$\{PGADMIN_DEFAULT_PASSWORD:\?[^}]+\}"/',
            $compose,
            'PGADMIN_DEFAULT_PASSWORD must be required via `${VAR:?…}` interpolation'
        );
    }

    public function test_legacy_pgadmin_credentials_cannot_authenticate(): void
    {
        // The compose-network DNS name `pgadmin` resolves to the pgAdmin
        // container from inside the `app` container where this test
        // executes. If pgAdmin is not reachable (e.g., the test runner
        // is invoked outside `docker compose`), skip the live check —
        // the static guarantees above already fence the regression.
        try {
            $resp = Http::timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->asForm()
                ->post('http://pgadmin:80/authenticate/login', [
                    'email'    => 'admin@local.dev',
                    'password' => 'admin',
                ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('pgAdmin service is not reachable from this test runner');
        }

        // pgAdmin's authentication endpoint always returns a 302 — the
        // location header is the only signal that distinguishes success
        // from failure:
        //
        //   * SUCCESSFUL login → 302 with `Location: /browser/` (the
        //     authenticated landing page).
        //   * FAILED login     → 302 with `Location: /login` (kicks the
        //     user back to the login form).
        //
        // Asserting the location header does NOT route the caller to
        // /browser is the narrowest possible regression check that
        // proves the historical `admin@local.dev / admin` defaults no
        // longer authenticate.
        $this->assertSame(302, $resp->status(),
            'pgAdmin /authenticate/login should redirect (302) regardless of outcome');

        $location = $resp->header('Location') ?? '';
        $this->assertStringNotContainsString(
            '/browser',
            $location,
            'Legacy pgAdmin credentials (admin@local.dev / admin) must NOT authenticate — security regression!'
        );
        $this->assertStringContainsString(
            '/login',
            $location,
            'Failed pgAdmin login must redirect back to /login'
        );
    }
}
