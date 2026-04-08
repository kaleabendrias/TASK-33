# syntax=docker/dockerfile:1.6
#
# Zero-Config-File pgAdmin image. Wraps the official dpage/pgadmin4
# image with an entrypoint that GENERATES the admin credentials
# inside the running container before pgAdmin's own setup script
# fires. The host never has to set PGADMIN_DEFAULT_EMAIL or
# PGADMIN_DEFAULT_PASSWORD — `docker compose up` works as-is on
# a fresh machine, with no .env file and no shell exports.
#
FROM dpage/pgadmin4:latest

USER root

# Install the wrapper that runs before pgAdmin's stock entrypoint.
COPY docker/pgadmin/entrypoint.sh /usr/local/bin/zero-config-entrypoint.sh
RUN chmod +x /usr/local/bin/zero-config-entrypoint.sh

USER pgadmin

# Hand control to the wrapper. The wrapper generates ephemeral
# credentials, exports them as PGADMIN_DEFAULT_EMAIL /
# PGADMIN_DEFAULT_PASSWORD, then exec's the official entrypoint
# (preserved as the CMD argument) so all of pgAdmin's normal init
# logic runs unchanged.
ENTRYPOINT ["/usr/local/bin/zero-config-entrypoint.sh"]
CMD ["/entrypoint.sh"]
