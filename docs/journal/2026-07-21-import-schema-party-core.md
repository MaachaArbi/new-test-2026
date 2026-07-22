# Journal — 2026-07-21 — import schéma Party + Core

## Demandé

Documenter `reference/` ; importer uniquement ref-common → Party → extension groupes → Core ;
smoke tests d’invariants ; pas d’autres modules.

## Partie 1 — reference/

Présent à la racine : `backend-cadrage/`, `conceptual-models/`, `meta/`, `schemas/`, `README.md`.  
`schemas/` : **14** fichiers `schema-*.sql` (+ diffs additifs).  
README projet mis à jour (distinction `docs/` vivant vs `reference/` gelé).

## Migrations créées

| Version | Fichier source | Comportement |
|---|---|---|
| `Version20260721110100` | `schema-ref-common.sql` | exécuté (PDO exec) |
| `Version20260721110101` | `schema-party-account-v1.sql` | exécuté (inclut déjà group*) |
| `Version20260721110102` | `party-account-group-extension.diff` | **no-op versionné** (déjà dans Party) |
| `Version20260721110103` | `schema-core-identity-v1.sql` | exécuté |

Helper : `App\Shared\Infrastructure\Doctrine\AbstractReferenceSqlMigration`.  
Décision : `docs/decisions/2026-07-21-party-group-extension-already-in-schema.md`.

`doctrine:migrations:migrate --no-interaction` → OK (4 migrations).

## Tables confirmées (`\dt`)

`ref_language`, `ref_currency`, `party_role`, `party_role_translation`, `party_account`,
`party_account_address`, `party_account_role`, `party_account_person_identity`,
`party_account_organization_identity`, `party_account_document`, `party_function`,
`party_function_translation`, `party_account_function`, `party_account_attribute`,
`party_account_office`, `party_account_office_relation`, `party_account_group_type`,
`party_account_group`, `party_account_group_member`, `core_credential`
(+ `doctrine_migration_versions`).

Liste demandée : toutes présentes.

## Smoke tests (jetables, résultats)

| # | Invariant | Résultat |
|---|---|---|
| 1 | email invalide → `ck_party_account_email_format` | **PASS** |
| 2 | nature `robot` → CHECK nature | **PASS** |
| 3 | emails actifs casse différente → `uq_party_account_email_active` | **PASS** |
| 4 | double `party_account_role` actif → `uq_party_account_role_active` | **PASS** |
| 5 | `party_account_group_type` = `commercial` seul | **PASS** |
| 6 | double `core_credential` (provider, provider_user_id) → `uq_core_credential_provider_identity` | **PASS** |

## Hors `/home/ubuntu/ostravel/`

Aucune modification hors projet (docker compose exec / psql uniquement).
