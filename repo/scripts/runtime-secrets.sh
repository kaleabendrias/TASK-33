#!/usr/bin/env bash
#
# runtime-secrets.sh — Sourced helper that generates ephemeral, high
# entropy credentials for sensitive infrastructure services and exposes
# them to a `docker compose` invocation via process substitution
# (`--env-file <(...)`). The credentials live only in the lifetime of
# the calling shell — they are NEVER written to disk.
#
# Zero-Config-File security model:
#   * No .env file is read or created.
#   * No credential is hard-coded in docker-compose.yml.
#   * If a caller refuses to generate or supply the credentials,
#     `docker compose up` aborts with a hard error from the
#     `${VAR:?…}` interpolation guards in docker-compose.yml.
#
# Usage (sourced):
#   . scripts/runtime-secrets.sh
#   generate_runtime_secrets         # populates exported env vars
#   compose_env_file=$(secrets_env_file)
#   docker compose --env-file "$compose_env_file" up -d --build --wait
#

set -euo pipefail

# Generate a URL-safe high-entropy string of N bytes.  We prefer
# /dev/urandom + base64 because it works on every supported host
# (Linux, macOS, BSD) without depending on the openssl CLI.
_random_b64() {
    local bytes="${1:-32}"
    head -c "$bytes" /dev/urandom | base64 | tr -d '\n=+/' | cut -c1-"$bytes"
}

# Populate ephemeral credentials in the calling shell. Existing values
# are respected so a CI orchestrator can inject its own secrets through
# the host environment without this helper clobbering them — but if
# nothing is set, we generate something secure on the fly.
generate_runtime_secrets() {
    if [ -z "${PGADMIN_DEFAULT_EMAIL:-}" ]; then
        # Random local-part keeps the email opaque so the address itself
        # is not a guessable attack surface. We use the IANA-reserved
        # `example.com` TLD because pgAdmin's email validator rejects
        # `.local` and similar special-use names with a hard error.
        export PGADMIN_DEFAULT_EMAIL="ops-$(_random_b64 12)@example.com"
    fi
    if [ -z "${PGADMIN_DEFAULT_PASSWORD:-}" ]; then
        # 32 base64 chars ≈ 192 bits of entropy. Far above any
        # practical brute-force threshold for an interactive admin UI.
        export PGADMIN_DEFAULT_PASSWORD="$(_random_b64 32)"
    fi
}

# Emit the secrets in `KEY=VALUE` form, suitable for `docker compose
# --env-file <(secrets_env_file)`. We deliberately do NOT write this
# anywhere on disk — callers should pipe the function's stdout through
# a process substitution.
secrets_env_file() {
    : "${PGADMIN_DEFAULT_EMAIL:?secrets not generated — call generate_runtime_secrets first}"
    : "${PGADMIN_DEFAULT_PASSWORD:?secrets not generated — call generate_runtime_secrets first}"
    printf 'PGADMIN_DEFAULT_EMAIL=%s\n' "$PGADMIN_DEFAULT_EMAIL"
    printf 'PGADMIN_DEFAULT_PASSWORD=%s\n' "$PGADMIN_DEFAULT_PASSWORD"
}
