#!/usr/bin/env bash
#
# start.sh — Bring the ServicePlatform stack up under the
# Zero-Config-File security model.
#
# Sensitive infrastructure credentials (currently the pgAdmin admin
# email + password) are generated INSIDE the pgAdmin container by its
# wrapper entrypoint at startup — see docker/pgadmin/Dockerfile and
# docker/pgadmin/entrypoint.sh. The host never sets, reads, or
# persists them. `docker compose up` works on a fresh machine with
# no shell exports and no .env file.
#
# Usage:
#   ./start.sh
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

GREEN='\033[0;32m'; BOLD='\033[1m'; NC='\033[0m'
info() { echo -e "${GREEN}[INFO]${NC}  $*"; }

info "Bringing the stack up under the Zero-Config-File model..."
docker compose up -d --build --wait

info "Stack is healthy."

cat <<EOF

  ${BOLD}pgAdmin URL:${NC} http://localhost:5050

  pgAdmin generated its own ephemeral admin credentials inside the
  running container. Retrieve them with:

      docker compose logs pgadmin | grep -A2 'Zero-Config-File'

  They exist only inside this container instance. Running
  \`docker compose down\` destroys the container and rotates them
  on the next \`up\`.

EOF
