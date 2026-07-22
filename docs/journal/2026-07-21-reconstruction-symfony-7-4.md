# Journal — 2026-07-21 — reconstruction Symfony 7.4

## Demandé

- Remplacer Symfony 7.2 (EOL) par skeleton **7.4 LTS** sans flags de contournement
- Garder `docker-compose.yml`, `docker/`, `.env`
- Réinstaller packages (orm-pack, messenger, validator, phpunit, phpstan+bridges, php-cs-fixer, phpcpd, deptrac)
- Recréer structure modules + configs qualité
- Vérifier `docker compose up` + Doctrine
- Mettre en place `docs/` (STATUS, journal, decisions, backlog)
- Déplacer BOOTSTRAP-REPORT → `docs/journal/2026-07-21-bootstrap-initial.md`

## Fait

1. Contenu applicatif effacé ; infra Docker + `.env` + `docs/` conservés
2. `composer create-project symfony/skeleton:"7.4.*"` **sans** `--no-security-blocking` → OK
3. `composer show symfony/framework-bundle` → **v7.4.14**
4. Packages réinstallés sans bypass → OK (advisories : aucune)
5. Structure `src/Modules`, `src/Shared/{Domain,Application,Infrastructure}`, tests Unit/Integration/Shared
6. Configs : Doctrine XML, phpstan 9, php-cs-fixer PSR-12, deptrac, phpunit.dist.xml
7. `docker compose up -d` → OK ; DBAL `ok=1` ; HTTP 404 (pas de routes métier)
8. PIDs app existante inchangés (nginx 99392/178364/178366, next 183424, redis 182299, gunicorn 204571/204575/204576)
9. Bindings ostravel : `127.0.0.1:5432`, `127.0.0.1:8080` uniquement

## Commandes hors `/home/ubuntu/ostravel/`

Aucune nouvelle (Docker/swap déjà en place). `sudo chown` / `sudo ss` / `sudo rm` outils locaux uniquement sur le projet ou lecture ports.

## Blocage

`sebastian/phpcpd` v2 casse phpcpd **et** phpunit (voir `docs/decisions/2026-07-21-phpcpd-symfony-console.md`). **Arrêt pour validation.**
