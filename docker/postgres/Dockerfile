# PostgreSQL 16 + pg_partman 5.2.4 (partition native).
# Le paquet Alpine `postgresql-pg_partman` cible PG18 — incompatible avec
# cette image. Build depuis les sources taguées (§8).
# BGW désactivé (LLVM bitcode de l'image alpine incompatible) : maintenance
# via cron système dans le conteneur — voir docker/postgres/entrypoint.sh.
FROM postgres:16-alpine

ARG PG_PARTMAN_VERSION=5.2.4

RUN apk add --no-cache --virtual .build-deps \
        build-base \
        postgresql16-dev \
        git \
        ca-certificates \
    && git clone --depth 1 --branch "v${PG_PARTMAN_VERSION}" \
        https://github.com/pgpartman/pg_partman.git /tmp/pg_partman \
    && make -C /tmp/pg_partman clean \
    && make -C /tmp/pg_partman NO_BGW=1 \
    && make -C /tmp/pg_partman NO_BGW=1 install \
    && rm -rf /tmp/pg_partman \
    && apk del .build-deps \
    && apk add --no-cache busybox-suid

COPY entrypoint.sh /usr/local/bin/ostravel-postgres-entrypoint.sh
COPY crontab /etc/crontabs/postgres
RUN chmod +x /usr/local/bin/ostravel-postgres-entrypoint.sh \
    && chown root:root /etc/crontabs/postgres \
    && chmod 0600 /etc/crontabs/postgres

ENTRYPOINT ["/usr/local/bin/ostravel-postgres-entrypoint.sh"]
CMD ["postgres"]
