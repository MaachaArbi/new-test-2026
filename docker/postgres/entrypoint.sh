#!/bin/sh
# Lance crond (maintenance pg_partman horaire) puis l'entrypoint officiel.
set -eu
crond -b -l 8
exec docker-entrypoint.sh "$@"
