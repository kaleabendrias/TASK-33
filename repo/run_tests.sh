#!/usr/bin/env bash
set -euo pipefail

#######################################################################
# run_tests.sh — Boot services, create test DB, run both test suites
# with per-suite coverage, exit nonzero if either drops below 90%.
#
# Pre-baked dev tooling lives in the `development` Dockerfile target,
# so this script does NOT install composer/pcov at runtime — it only
# orchestrates the test invocations against the already-built image.
#######################################################################

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

MIN_COVERAGE=90

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BOLD='\033[1m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; }

# ── 1. Boot Docker (build the development target — pcov pre-baked) ──
info "Building & starting Docker services (development target)..."
docker compose down -v --remove-orphans 2>/dev/null || true
docker compose up -d --build --wait 2>&1 | tail -5

info "Waiting for healthy app container..."
timeout 180 bash -c 'until docker compose ps app --format json 2>/dev/null | grep -q healthy; do sleep 5; done' || {
    fail "App container did not become healthy"; docker compose logs app --tail 40; exit 1
}

# ── 2. Sanity check: pcov must already be loaded (it's baked in) ────
if ! docker compose exec -T app php -m | grep -q pcov; then
    fail "pcov is not loaded — the development image was not built correctly"
    exit 1
fi
info "pcov pre-baked into image ✓"

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
    docker compose exec -T app sh -c \
        "php -d memory_limit=1024M -d pcov.enabled=1 \
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
