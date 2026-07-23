## Reprise à froid

Journal — 2026-07-21 — import schéma Party + Core.
Documenter `reference/` ; importer uniquement ref-common → Party → extension groupes → Core ;
smoke tests d’invariants ; pas d’autres modules.
Présent à la racine : `backend-cadrage/`, `conceptual-models/`, `meta/`, `schemas/`, `README.md`.

## Origine

```
# TASK — Mettre à jour la documentation + importer le schéma SQL (Party + Core uniquement)

## Partie 1 — Documentation

Le dossier `reference/` a été déposé manuellement par l'utilisateur (contient
`backend-cadrage/`, `conceptual-models/`, `meta/`, `schemas/`, `README.md`) mais
n'apparaît nulle part dans le suivi. Corriger :

1. Vérifier que `reference/` existe bien à la racine du projet avec ses 4
   sous-dossiers + README.md — si absent ou incomplet, ARRÊTE-TOI et signale-le
   au lieu de continuer sur la Partie 2.
2. Mettre à jour `docs/STATUS.md` (réécriture complète, pas d'ajout partiel) pour
   inclure une ligne explicite confirmant la présence de `reference/` et un résumé
   de son contenu (nombre de modules disponibles dans `reference/schemas/`).
3. Ajouter dans le `README.md` du projet (racine) une section courte pointant vers
   `reference/README.md` pour qu'un nouvel arrivant (ou toi dans 3 mois) comprenne
   immédiatement la distinction entre `docs/` (vivant) et `reference/` (gelé,
   jamais modifié par un agent).

## Partie 2 — Import du schéma SQL (Party + Core uniquement, pas les 14 modules)

RAPPEL DE LA RÈGLE (déjà actée) : les migrations Doctrine ne génèrent JAMAIS de schéma
(`doctrine:migrations:diff` interdit). Elles ne servent qu'à VERSIONNER ET EXÉCUTER le
SQL déjà écrit dans `reference/schemas/`.

### Fichiers à importer, dans cet ordre exact (dépendances) :
1. `reference/schemas/schema-ref-common.sql` (ref_language, ref_currency)
2. `reference/schemas/schema-party-account-v1.sql`
3. `reference/schemas/party-account-group-extension.diff` (additif sur le précédent)
4. `reference/schemas/schema-core-identity-v1.sql`

NE PAS importer autre chose à ce stade : ni `diff-core-auth-avancee.sql` (session/MFA,
hors périmètre de cette première vague, voir `backend-cadrage/02-backend-module-index.md`),
ni `schema-permissions-config-v1.sql`, ni aucun autre module (Booking, Règlements,
etc.) — un seul module à la fois.

### Étapes
1. Créer une migration Doctrine par fichier ci-dessus (4 migrations), chacune
   chargeant le contenu du fichier `.sql`/`.diff` correspondant via `addSql()` (ou
   équivalent lisant le fichier), dans l'ordre listé.
2. Exécuter `docker compose exec php bin/console doctrine:migrations:migrate --no-interaction`
   et vérifier que les 4 s'exécutent sans erreur.
3. Vérifier avec `\dt` (psql) que les tables attendues existent : `party_account`,
   `party_role`, `party_account_role`, `party_function`, `party_account_function`,
   `party_account_group`, `party_account_group_member`, `party_account_address`,
   `party_account_person_identity`, `party_account_organization_identity`,
   `party_account_document`, `party_account_office`, `party_account_office_relation`,
   `core_credential`, `ref_language`, `ref_currency`.

### Tests de vérification (smoke tests, à écrire et exécuter, pas à supposer)
Rejoue ces vérifications d'invariants directement en SQL (script jetable, résultat
collé dans le journal, pas besoin de le conserver dans le repo) :
- Un INSERT dans `party_account` avec un email au format invalide doit être rejeté
  par `ck_party_account_email_format`
- Un INSERT dans `party_account` avec `nature` différent de `person`/`organization`
  doit être rejeté par le CHECK
- Deux comptes actifs (`deleted_at IS NULL`) avec le même email (casse différente)
  doivent violer `uq_party_account_email_active`
- Deux lignes actives (`valid_to IS NULL`) de `party_account_role` pour le même
  couple (account_id, role_code) doivent violer `uq_party_account_role_active`
- `party_account_group_type` ne doit contenir QUE la ligne `commercial` (seed initial)
- Deux `core_credential` actifs avec le même (provider, provider_user_id) doivent
  violer `uq_core_credential_provider_identity`

Si un de ces tests ne se comporte pas comme attendu, ARRÊTE-TOI et documente l'écart
dans `docs/decisions/` — ne corrige pas le schéma toi-même (il est figé, geré côté
conception BDD, pas côté backend).

## Documentation de fin de session
- `docs/journal/2026-07-2X-import-schema-party-core.md` : les 4 migrations créées,
  le résultat de chaque smoke test, la liste des tables confirmées présentes
- `docs/STATUS.md` : réécrire entièrement — ajouter une ligne "Base de données :
  schéma Party+Core importé (X tables)", garder le reste à jour
- `docs/backlog/todo.md` : noter que le bootstrap applicatif obligatoire décrit dans
  `reference/schemas/schema-party-account-v1.sql` (note d'implémentation #10 : créer
  le `party_account` représentant l'agence elle-même AVANT tout autre insert
  applicatif) reste à faire — ce sera la responsabilité du premier prompt Domain
  Party, pas de celui-ci

Réponds-moi avec le contenu de docs/STATUS.md et le résultat des smoke tests.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
