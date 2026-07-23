## Reprise à froid

Journal — 2026-07-21 — Clôture PATCH + DELETE PartyAccount.
`PATCH` / `DELETE /api/v1/party-accounts/{publicId}` sont **clos et validés**. Détail livré : `2026-07-21-party-account-update-delete-endpoints.md`. Décision soft-delete vs disable :…
`PATCH` / `DELETE /api/v1/party-accounts/{publicId}` sont **clos et validés**.
Détail livré : `2026-07-21-party-account-update-delete-endpoints.md`.

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Clôture PATCH + DELETE PartyAccount

## Contexte

`PATCH` / `DELETE /api/v1/party-accounts/{publicId}` sont **clos et validés**.
Détail livré : `2026-07-21-party-account-update-delete-endpoints.md`.
Décision soft-delete vs disable :
`docs/decisions/2026-07-21-soft-delete-vs-disable-party-account.md`.

## Périmètre clos

- `PATCH` partiel : `displayName` → `updateDisplayName()` ; `isDisabled`
  true/false → `disable()` / `enable()` ; body vide / aucun champ applicable
  → `party_account.no_changes_provided` (400) ; introuvable → 404
- `DELETE` soft : `PartyAccount::delete()` ; 1er → **204** ; déjà soft-deleted
  → **200** ; inexistant → 404 ; GET suivant → 404
- Domain : `enable()` ajouté ; `delete()` idempotent (garde `deletedAt`)
- Repo : `findByPublicId` / `findByEmail` excluent soft-deleted ;
  `findByPublicIdIncludingDeleted` pour DELETE idempotent
- `PartyAccountResponse` : + `isDisabled` ; jamais `id` interne
- `PartyAccountHttpSupport` partagé Create/Update (anti-phpcpd)
- Traductions `party_account.no_changes_provided` (en/fr/ar)
- Tests HTTP : displayName, disable/enable, body vide 400, 404, DELETE+GET,
  2ᵉ DELETE 200, 401 sans JWT (compte auth ≠ compte cible)

## Corrections / rattrapage de clôture

1. `enable()` Domain manquant au moment du PATCH — ajouté
2. Tests : JWT sur compte distinct du compte disable/delete (sinon 401)
3. Factorisation JSON/violations Create↔Update via `PartyAccountHttpSupport`

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 131 tests, 733 assertions

**phpstan** (exit 0) — No errors (`memory_limit=512M`)

**deptrac** (exit 0) — Violations 0 · Allowed 284 · Uncovered 166

**phpcpd** (exit 0) — No clones found (seuil 10 / 20)
