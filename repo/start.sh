#!/usr/bin/env bash
#
# start.sh — Bring the ServicePlatform stack up under the Zero-Config
# File security model. Sensitive infrastructure credentials are
# generated in-memory at invocation time and injected into
# `docker compose up` via process substitution. Nothing is written
# to disk; nothing is hard-coded in docker-compose.yml.
#
# Usage:
#   ./start.sh                     # generate fresh ephemeral secrets
#   PGADMIN_DEFAULT_EMAIL=… \
#   PGADMIN_DEFAULT_PASSWORD=… \
#       ./start.sh                 # honour pre-injected secrets
#
# After the stack is up, the generated pgAdmin credentials are echoed
# ONCE to the operator's terminal (and only there). They are NOT
# persisted to .env, /tmp, or anywhere else.
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# shellcheck disable=SC1091
. "$SCRIPT_DIR/scripts/runtime-secrets.sh"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BOLD='\033[1m'; NC='\033[0m'
info() { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $*"; }

# 1. Generate (or honour pre-injected) ephemeral secrets.
generate_runtime_secrets

info "Bringing the stack up under the Zero-Config-File model..."

# 2. Inject secrets into docker compose via process substitution.
#    `--env-file <(...)` reads the substituted file descriptor as
#    if it were an .env file but never materialises one on disk.
#    The `${VAR:?…}` guards in docker-compose.yml will abort the
#    `up` if either variable is missing or empty, so a partial
#    secret injection cannot silently fall back to defaults.
docker compose --env-file <(secrets_env_file) up -d --build --wait

info "Stack is healthy."

# 3. Print the generated pgAdmin credentials ONCE so the operator can
#    log in. Anyone who needs them later must restart the stack — by
#    design, there is nowhere to look them up.
cat <<EOF

  ${BOLD}pgAdmin credentials (ephemeral, runtime-only):${NC}
    URL:      http://localhost:5050
    Email:    ${PGADMIN_DEFAULT_EMAIL}
    Password: ${PGADMIN_DEFAULT_PASSWORD}

  These credentials exist only in this shell and inside the running
  pgAdmin container. They are NOT written to any file. Restarting
  the stack will rotate them.

EOF
