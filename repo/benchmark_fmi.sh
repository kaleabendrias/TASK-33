#!/usr/bin/env bash
set -euo pipefail

#######################################################################
# benchmark_fmi.sh — Reproducible First Meaningful Interaction (FMI)
#                    benchmark against the running stack.
#
# Measures the time-to-meaningful-paint for each public-facing route by
# requesting the server-rendered HTML payload N times, computing the
# median, and asserting it is below the 2.5-second KPI budget.
#
# Usage:
#   ./benchmark_fmi.sh                      # default 10 samples
#   ./benchmark_fmi.sh 30                   # 30 samples per route
#   APP_URL=http://localhost:8080 ./benchmark_fmi.sh
#
# Exit code is non-zero if ANY route exceeds the budget — suitable for
# wiring into CI as a release gate.
#######################################################################

SAMPLES="${1:-10}"
APP_URL="${APP_URL:-http://localhost:8080}"
BUDGET_MS=2500
ARTIFACT_DIR="${ARTIFACT_DIR:-/tmp/fmi_benchmark}"
mkdir -p "$ARTIFACT_DIR"
ARTIFACT_FILE="$ARTIFACT_DIR/fmi_$(date +%Y%m%d_%H%M%S).json"

GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'; NC='\033[0m'

# ── Authenticate as a user we can use to fetch authenticated routes ─
echo "Logging in as the seeded admin to obtain a JWT…"
TOKEN=$(curl -sf -X POST "${APP_URL}/api/auth/login" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"username":"admin","password":"Admin@12345678"}' \
    | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])" \
    || { echo -e "${RED}Failed to obtain JWT — is the stack running?${NC}"; exit 2; })
echo "Token: ${TOKEN:0:24}…"

# ── Routes to measure ───────────────────────────────────────────────
# Each entry is "Label|HTTP-METHOD|PATH". Authenticated routes use the
# bearer token; the public health endpoint exercises a no-auth path.
ROUTES=(
    "health|GET|/api/health"
    "service-areas|GET|/api/service-areas"
    "bookings/items|GET|/api/bookings/items"
    "orders|GET|/api/orders"
    "auth/me|GET|/api/auth/me"
)

declare -A RESULTS_MEDIAN
declare -A RESULTS_P95
declare -A RESULTS_MAX

# ── Helper: median (works on integer ms values) ─────────────────────
median() {
    local sorted=($(printf '%s\n' "$@" | sort -n))
    local n=${#sorted[@]}
    local mid=$((n / 2))
    if (( n % 2 == 0 )); then
        echo $(( (sorted[mid - 1] + sorted[mid]) / 2 ))
    else
        echo "${sorted[mid]}"
    fi
}

p95() {
    local sorted=($(printf '%s\n' "$@" | sort -n))
    local n=${#sorted[@]}
    local idx=$((n * 95 / 100))
    (( idx >= n )) && idx=$((n - 1))
    echo "${sorted[idx]}"
}

# ── Warm-up: Opcode/JIT cache priming ───────────────────────────────
echo "Warming up routes (3 hits each)..."
for entry in "${ROUTES[@]}"; do
    IFS='|' read -r label method path <<< "$entry"
    for _ in 1 2 3; do
        curl -sf -o /dev/null -X "$method" "${APP_URL}${path}" \
            -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' || true
    done
done

# ── Benchmark loop ──────────────────────────────────────────────────
echo ""
echo "Running ${SAMPLES} samples per route…"
echo ""
printf "%-25s %10s %10s %10s\n" "Route" "median" "p95" "max"
printf "%-25s %10s %10s %10s\n" "-----" "------" "----" "----"

FINAL_EXIT=0
JSON_ROUTES=""

for entry in "${ROUTES[@]}"; do
    IFS='|' read -r label method path <<< "$entry"
    samples=()
    for _ in $(seq 1 "$SAMPLES"); do
        # curl prints elapsed time in seconds with microsecond precision.
        elapsed=$(curl -sf -o /dev/null -w '%{time_total}' \
            -X "$method" "${APP_URL}${path}" \
            -H "Authorization: Bearer $TOKEN" \
            -H 'Accept: application/json' \
            -H 'Cache-Control: no-cache' \
            || echo "9.999")
        # Convert seconds → integer ms
        ms=$(awk -v t="$elapsed" 'BEGIN { printf "%d", t * 1000 }')
        samples+=("$ms")
    done

    med=$(median "${samples[@]}")
    p95v=$(p95 "${samples[@]}")
    max=$(printf '%s\n' "${samples[@]}" | sort -n | tail -1)
    RESULTS_MEDIAN[$label]=$med
    RESULTS_P95[$label]=$p95v
    RESULTS_MAX[$label]=$max

    color=$GREEN
    if (( med > BUDGET_MS )); then
        color=$RED
        FINAL_EXIT=1
    fi
    printf "%-25s ${color}%9dms %9dms %9dms${NC}\n" "$label" "$med" "$p95v" "$max"

    JSON_ROUTES+="    {\"route\":\"$label\",\"path\":\"$path\",\"median_ms\":$med,\"p95_ms\":$p95v,\"max_ms\":$max,\"samples\":[$(IFS=,; echo "${samples[*]}")]},"
done

JSON_ROUTES=${JSON_ROUTES%,}

# ── CI artifact ─────────────────────────────────────────────────────
cat > "$ARTIFACT_FILE" <<JSON
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "budget_ms": $BUDGET_MS,
  "samples_per_route": $SAMPLES,
  "app_url": "$APP_URL",
  "routes": [
$JSON_ROUTES
  ]
}
JSON

echo ""
echo "Artifact written to: $ARTIFACT_FILE"
echo ""

if [[ $FINAL_EXIT -eq 0 ]]; then
    echo -e "${GREEN}PASS${NC}: every route's median FMI is under the ${BUDGET_MS}ms budget."
else
    echo -e "${RED}FAIL${NC}: at least one route exceeded the ${BUDGET_MS}ms FMI budget."
fi
exit $FINAL_EXIT
