# Journal — Liste paginée PartyAccounts (DBAL / ADR-003)

Date : 2026-07-21

## Livré

- `GET /api/v1/party-accounts?page&limit&nature&search`
- `ListPartyAccountsQuery` / `Handler` / `Result` — SQL DBAL pur, COUNT séparé,
  pas de réhydratation Domain
- Réponse `{data, meta:{page,limit,total,totalPages}}` — jamais `id` interne
- `limit > 100` → **422** (pas de plafonnage silencieux)
- page hors total → `data: []`, meta cohérente
- Deptrac : Application autorisé à Doctrine pour Query handlers (ADR-003)

## Indexes (schéma déjà importé)

Présents, rien à ajouter côté BDD :

- `idx_party_account_nature` (nature, `deleted_at IS NULL`)
- `idx_party_account_display_name_trgm` (GIN `pg_trgm` sur `display_name`)
  — adapté à `ILIKE '%…%'`

Aucun index manquant à remonter au chat DB pour cet endpoint.

## Suite

Update/delete PartyAccount HTTP si besoin ; premier front jetable possible.

## Clôture

Rattrapage + résultats finaux des 4 outils :
`2026-07-21-list-party-accounts-endpoint-cloture.md`.

