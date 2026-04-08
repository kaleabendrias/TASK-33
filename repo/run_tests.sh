#!/usr/bin/env bash
set -euo pipefail

#######################################################################
# run_tests.sh — Boot services, create test DB, run both test suites
# with per-suite coverage, exit nonzero if either drops below 90%.
#
# Coverage strategy: pcov is built into the image and we load it via
# an explicit `php -d extension=/abs/path/to/pcov.so` flag at command
# launch. We do NOT depend on conf.d/.ini scanning, on PHP_INI_SCAN_DIR,
# or on `php -m` listing pcov — those have proven fragile across hosts
# (Alpine apk index hiccups during build, host seccomp profiles, stale
# bind-mounts). Locating the .so file on disk is unambiguous and works
# regardless of how the runtime environment was set up.
#######################################################################

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

MIN_COVERAGE=90

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BOLD='\033[1m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; }

# ── 0. Generate ephemeral runtime secrets (Zero-Config-File model) ──
# pgAdmin credentials are never hard-coded or persisted. We generate
# high-entropy values in this shell and pipe them to docker compose
# via `--env-file <(...)` process substitution. The ${VAR:?...} guards
# in docker-compose.yml ensure the stack refuses to start if either
# variable is unset or empty.
# shellcheck disable=SC1091
. "$SCRIPT_DIR/scripts/runtime-secrets.sh"
generate_runtime_secrets
info "Generated ephemeral pgAdmin credentials for this run (not persisted)"

# ── 1. Boot Docker ──────────────────────────────────────────────────
info "Building & starting Docker services..."
docker compose --env-file <(secrets_env_file) down -v --remove-orphans 2>/dev/null || true
docker compose --env-file <(secrets_env_file) up -d --build --wait 2>&1 | tail -10

info "Waiting for healthy app container..."
timeout 180 bash -c 'until docker compose ps app --format json 2>/dev/null | grep -q healthy; do sleep 5; done' || {
    fail "App container did not become healthy"; docker compose logs app --tail 40; exit 1
}

# ── 2. Locate pcov.so on disk (do NOT rely on `php -m`) ─────────────
# Searching the extension dir is unambiguous: if pcov.so exists, we
# can load it via `php -d extension=...`. The previously-used
# `php -m | grep pcov` precheck depended on conf.d/scan-dir discovery
# which silently failed on some hosts even when the extension was
# present and loadable.
info "Locating pcov.so..."
PCOV_SO="$(docker compose exec -T app sh -c \
    'php -r "echo ini_get(\"extension_dir\");"' 2>/dev/null)/pcov.so"

if ! docker compose exec -T app test -f "$PCOV_SO"; then
    fail "pcov.so not found at expected path: $PCOV_SO"
    docker compose exec -T app sh -c 'find /usr/local/lib/php -name pcov.so 2>/dev/null || true'
    exit 1
fi
info "pcov.so located at: $PCOV_SO"

# Verify the runtime can actually load it via -d. This is a real
# functional check (not a conf.d/scan-dir check) and exercises the
# exact loading mechanism we use to run the suite below.
if ! docker compose exec -T app php -d "extension=$PCOV_SO" -r 'exit(extension_loaded("pcov") ? 0 : 1);'; then
    fail "pcov.so exists but PHP cannot load it via -d extension=..."
    exit 1
fi
info "pcov loadable via -d extension ✓"

# ── 3. Create the test database (idempotent) ────────────────────────
info "Creating test database..."
docker compose exec -T postgres psql -U app_user -d service_platform \
    -c "CREATE DATABASE service_platform_test OWNER app_user;" 2>/dev/null || true

# ── Helper: run a single suite ──────────────────────────────────────
run_suite() {
    local suite_name="$1"
    local config_file="$2"

    info "Migrating test DB (fresh) for ${suite_name}..."
    docker compose exec -T app sh -c \
        'DB_DATABASE=service_platform_test php artisan migrate:fresh --force --no-interaction --quiet' 2>/dev/null

    info "Running ${suite_name}..."
    # Load pcov via explicit -d extension=/abs/path so we never depend
    # on conf.d/.ini being scanned at startup. -d pcov.enabled=1
    # activates collection; the phpunit XML provides the include paths.
    docker compose exec -T app sh -c \
        "php -d memory_limit=1024M \
             -d extension=${PCOV_SO} \
             -d pcov.enabled=1 \
             vendor/bin/phpunit -c ${config_file} \
                --coverage-text --colors=never 2>&1" \
    | tee "/tmp/${suite_name}_output.txt"

    local exit_code=${PIPESTATUS[0]}
    local cov_pct
    cov_pct=$(grep -oP '^\s+Lines:\s+\K[\d.]+(?=%)' "/tmp/${suite_name}_output.txt" | head -1)
    cov_pct=${cov_pct:-0}

    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    if [[ $exit_code -eq 0 ]]; then
        info "${suite_name}: ALL TESTS PASSED"
    else
        fail "${suite_name}: TESTS FAILED (exit ${exit_code})"
    fi
    echo -e "  Line coverage: ${BOLD}${cov_pct}%${NC}  (minimum: ${MIN_COVERAGE}%)"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""

    eval "${suite_name//-/_}_exit=${exit_code}"
    eval "${suite_name//-/_}_cov=${cov_pct}"
}

# ── 4. Run unit_tests ───────────────────────────────────────────────
run_suite "unit_tests" "phpunit.unit.xml"
UNIT_EXIT=${unit_tests_exit:-1}
UNIT_COV=${unit_tests_cov:-0}

# ── 5. Run API_tests ────────────────────────────────────────────────
run_suite "API_tests" "phpunit.api.xml"
API_EXIT=${API_tests_exit:-1}
API_COV=${API_tests_cov:-0}

# ── 6. Final report ─────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║            TEST RESULTS SUMMARY                  ║"
echo "╠══════════════════════════════════════════════════╣"
printf "║  unit_tests:  %-6s  Coverage: %6s%%           ║\n" \
    "$([ "${UNIT_EXIT}" -eq 0 ] && echo "PASS" || echo "FAIL")" "${UNIT_COV}"
printf "║  API_tests:   %-6s  Coverage: %6s%%           ║\n" \
    "$([ "${API_EXIT}" -eq 0 ] && echo "PASS" || echo "FAIL")" "${API_COV}"
echo "║  Minimum required: ${MIN_COVERAGE}%                         ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

FINAL=0
[ "${UNIT_EXIT}" -ne 0 ] && { fail "unit_tests FAILED"; FINAL=1; }
[ "${API_EXIT}"  -ne 0 ] && { fail "API_tests FAILED"; FINAL=1; }

check_cov() {
    local n="$1" c="$2"; local i=${c%%.*}
    if [[ "${i:-0}" -lt "${MIN_COVERAGE}" ]]; then
        fail "${n} coverage ${c}% < ${MIN_COVERAGE}%"; return 1
    fi
    info "${n} coverage ${c}% >= ${MIN_COVERAGE}% ✓"; return 0
}

check_cov "unit_tests" "${UNIT_COV}" || FINAL=1
check_cov "API_tests"  "${API_COV}"  || FINAL=1

exit ${FINAL}
