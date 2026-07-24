# OsTravel — backend Symfony

Stack isolée Docker Compose (`COMPOSE_PROJECT_NAME=ostravel`).

**Suivi projet (vivant) :** voir [`docs/STATUS.md`](docs/STATUS.md).

**Référence figée (lecture seule) :** voir [`reference/README.md`](reference/README.md).
`docs/` = journal / décisions / backlog tenus à jour par les sessions.
`reference/` = cadrage + modèles + schémas SQL gelés — **jamais modifié par un agent**.

## Quick start

```bash
cd /home/ubuntu/ostravel
composer install
./tools/install-tools.sh
docker compose up -d --build
docker compose exec php php bin/console lexik:jwt:generate-keypair
curl -I http://127.0.0.1:8080/
```

PostgreSQL : `127.0.0.1:5432` — API Nginx : `127.0.0.1:8080`

### Déploiement BDD — pg_partman (§8, obligatoire)

Après la chaîne des 16 schémas et **avant** la première utilisation :

```bash
docker compose exec -T postgres psql -U ostravel -d ostravel -v ON_ERROR_STOP=1 \
  -f - < docker/postgres/sql/drop_default_partitions.sql
docker compose exec -T postgres psql -U ostravel -d ostravel -v ON_ERROR_STOP=1 \
  -f - < docker/postgres/sql/pg_partman_setup.sql
```

Détail : [`docs/decisions/2026-07-24-pg-partman-deploiement.md`](docs/decisions/2026-07-24-pg-partman-deploiement.md).
Surveillance (seuil &lt; 2 mois d'avance) : [`docs/ops/pg-partman-surveillance.md`](docs/ops/pg-partman-surveillance.md).


JWT : clés RSA dans `config/jwt/*.pem` (gitignorées). Générer via `lexik:jwt:generate-keypair`.
Login : `POST /api/v1/auth/login` `{email,password}` → `{token}`.
Bootstrap credential admin : `app:core:bootstrap-admin-credential <password>` (après `app:party:bootstrap-agency`).

### Purge comptes de test (CLI manuelle uniquement)

`app:party:purge-test-accounts` — **dev/test seulement**, dry-run par défaut,
`--execute` pour supprimer. **Ne jamais exposer via HTTP** ni appeler
automatiquement (cron, listener, CI destructive). Voir
`docs/journal/2026-07-21-purge-test-accounts.md`.

## Qualité

```bash
docker compose exec php vendor/bin/phpstan analyse -c phpstan.dist.neon
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec php php tools/phpcpd.phar --min-lines 10 --min-tokens 20 src/
docker compose exec php vendor/bin/deptrac analyse --config-file=deptrac.yaml
docker compose exec php vendor/bin/phpunit
```
