# Journal — Endpoints PATCH + DELETE PartyAccount

Date : 2026-07-21

## Livré

- `PATCH /api/v1/party-accounts/{publicId}` — mise à jour partielle
  (`displayName`, `isDisabled` → `disable()` / `enable()`). Body vide /
  aucun champ applicable → `party_account.no_changes_provided` (400).
- `DELETE /api/v1/party-accounts/{publicId}` — soft-delete Domain
  (`PartyAccount::delete()`). Premier appel → **204** ; déjà soft-deleted →
  **200** (idempotent) ; inexistant → **404**.
- `PartyAccount::enable()` ajouté (manquant alors que PATCH le requiert).
- `findByPublicId()` / `findByEmail()` excluent `deleted_at IS NOT NULL`.
  `findByPublicIdIncludingDeleted()` pour DELETE idempotent.
- `PartyAccountResponse` expose aussi `isDisabled` (jamais `id` interne).

## Architecture

Même style que Create/Get : Controller HTTP → Handler Application → Domain /
Repository. Erreurs Domain via ExceptionListener + catalogue `errors`.

## Suite

CRUD Party HTTP complet. Prêt pour le vrai front.

Clôture : `2026-07-21-party-account-update-delete-endpoints-cloture.md`.
