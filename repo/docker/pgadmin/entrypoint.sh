#!/bin/sh
#
# Zero-Config-File pgAdmin entrypoint wrapper.
#
# This script runs INSIDE the pgAdmin container, before pgAdmin's own
# /entrypoint.sh, and is responsible for producing the admin
# credentials. The credentials never leave the container's lifetime:
#
#   * On first start (no persisted file), generate high-entropy
#     random values for the email and password and persist them at
#     /var/lib/pgadmin/.runtime_credentials with mode 600.
#
#   * On subsequent starts of the SAME container (e.g. `docker compose
#     restart`), reuse the persisted file so the existing pgAdmin
#     SQLite user database (which embeds a bcrypt hash of the
#     password) keeps matching what the operator was told.
#
#   * On `docker compose down` the container — and therefore the
#     persisted credential file — is destroyed; the next `up`
#     gets a fresh random pair.
#
# The credentials are echoed to stdout exactly once so the operator
# can retrieve them via `docker compose logs pgadmin`. They are
# never written anywhere on the host.
#

set -e

CRED_FILE=/var/lib/pgadmin/.runtime_credentials

# Make sure the data directory exists with the right ownership before
# we try to write into it. The official pgAdmin image creates this
# during its own init, but on the very first start the directory may
# not exist yet — `mkdir -p` is idempotent and harmless.
mkdir -p /var/lib/pgadmin

# Generate a URL-safe base64 string of approximately N characters from
# /dev/urandom. Busybox in the official image ships head + base64 + tr,
# so this works without installing anything.
gen_token() {
    bytes="$1"
    head -c "$bytes" /dev/urandom | base64 | tr -d '+/=\n' | cut -c1-"$bytes"
}

if [ ! -s "$CRED_FILE" ]; then
    # Random local-part keeps the email opaque so the address itself
    # is not a guessable attack surface. We use the IANA-reserved
    # `example.com` TLD because pgAdmin's email validator rejects
    # `.local` and similar special-use names with a hard error.
    PGADMIN_DEFAULT_EMAIL="ops-$(gen_token 12)@example.com"
    # 32 base64 chars ≈ 192 bits of entropy. Far above any practical
    # brute-force threshold for an interactive admin UI.
    PGADMIN_DEFAULT_PASSWORD="$(gen_token 32)"

    # Persist with strict permissions. The directory is private to the
    # pgadmin user inside the container; nothing on the host can read it.
    umask 077
    {
        printf 'PGADMIN_DEFAULT_EMAIL=%s\n' "$PGADMIN_DEFAULT_EMAIL"
        printf 'PGADMIN_DEFAULT_PASSWORD=%s\n' "$PGADMIN_DEFAULT_PASSWORD"
    } > "$CRED_FILE"
    chmod 600 "$CRED_FILE" 2>/dev/null || true
fi

# shellcheck disable=SC1090
. "$CRED_FILE"
export PGADMIN_DEFAULT_EMAIL PGADMIN_DEFAULT_PASSWORD

# One-shot stdout banner so the operator can run
# `docker compose logs pgadmin` and retrieve the credentials. There
# is intentionally no other place to look them up.
cat <<EOF
============================================================
  pgAdmin Zero-Config-File ephemeral credentials
  Email:    ${PGADMIN_DEFAULT_EMAIL}
  Password: ${PGADMIN_DEFAULT_PASSWORD}
  These exist only inside this container. Restarting the
  stack with \`docker compose down\` rotates them.
============================================================
EOF

# Hand off to the official pgAdmin entrypoint with the original CMD.
exec "$@"
