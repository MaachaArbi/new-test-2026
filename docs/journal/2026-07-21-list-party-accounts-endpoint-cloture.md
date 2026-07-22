# Journal — 2026-07-21 — Clôture liste paginée PartyAccounts

## Contexte

`GET /api/v1/party-accounts` (DBAL / ADR-003) est **clos et validé**.
Détail livré : `2026-07-21-list-party-accounts-endpoint.md`.

## Périmètre clos

- `ListPartyAccountsQuery` / `Handler` / `Result` — SQL DBAL pur, COUNT séparé,
  `deleted_at IS NULL`, pas de réhydratation Domain
- Réponse `{data, meta:{page,limit,total,totalPages}}` — jamais `id` interne
- Filtres `nature` / `search` (ILIKE display_name) ; pagination `page` / `limit`
- `limit > MAX_LIMIT (100)` → **422** explicite (pas de plafonnage silencieux)
- page hors total → `data: []`, meta cohérente (`total` / `totalPages` inchangés)
- Validation `nature` via `PartyAccountNature::cases()` (source unique Domain)
- `ValidationFailedJsonResponseFactory` partagé (anti-phpcpd 422)
- Deptrac : Application autorisé à Doctrine pour Query handlers ADR-003
- Indexes schéma déjà présents (`nature`, GIN `pg_trgm` display_name) — rien
  à remonter au chat DB
- 6 tests HTTP intégration (pagination jeu connu, nature, search, limit 422,
  page hors total, 401 sans JWT)

## Corrections / rattrapage de clôture

1. Remplacement liste en dur `['person', 'organization']` par
   `PartyAccountNature::cases()` mappé en valeurs string
2. Note backlog : surveiller duplication `deleted_at IS NULL` dans les Queries
   DBAL (helper partagé si 3+ occurrences futures)

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 121 tests, 669 assertions

**phpstan** (exit 0) — No errors (`memory_limit=512M`)

**deptrac** (exit 0) — Violations 0 · Allowed 258 · Uncovered 132

**phpcpd** (exit 0) — No clones found (seuil 10 / 20)
