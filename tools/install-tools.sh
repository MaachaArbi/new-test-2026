#!/usr/bin/env bash
# Installe les binaires d'outils non gérés par Composer (idempotent).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TOOLS_DIR="${ROOT}/tools"
PHPCPD_PHAR="${TOOLS_DIR}/phpcpd.phar"
PHPCPD_URL="https://phar.phpunit.de/phpcpd.phar"

mkdir -p "${TOOLS_DIR}"

if [[ -f "${PHPCPD_PHAR}" ]]; then
  echo "phpcpd.phar déjà présent : ${PHPCPD_PHAR}"
else
  echo "Téléchargement de phpcpd.phar…"
  if command -v wget >/dev/null 2>&1; then
    wget -q "${PHPCPD_URL}" -O "${PHPCPD_PHAR}"
  elif command -v curl >/dev/null 2>&1; then
    curl -fsSL "${PHPCPD_URL}" -o "${PHPCPD_PHAR}"
  else
    echo "Erreur : wget ou curl requis pour télécharger ${PHPCPD_URL}" >&2
    exit 1
  fi
  chmod +x "${PHPCPD_PHAR}"
  echo "Installé : ${PHPCPD_PHAR}"
fi

if command -v php >/dev/null 2>&1; then
  php "${PHPCPD_PHAR}" --version
elif command -v docker >/dev/null 2>&1 && [[ -f "${ROOT}/docker-compose.yml" ]]; then
  (cd "${ROOT}" && docker compose exec -T php php tools/phpcpd.phar --version) || \
    echo "PHAR téléchargé (vérifier : docker compose exec php php tools/phpcpd.phar --version)"
else
  echo "PHAR téléchargé. Vérifier avec : php tools/phpcpd.phar --version"
fi
