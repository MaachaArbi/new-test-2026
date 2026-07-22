# Journal — 2026-07-21 — Premier Controller PartyAccount + ExceptionListener

## Contexte

Premier endpoint HTTP : `GET /api/v1/party-accounts/{publicId}`. Objectif :
prouver la chaîne routing → DTO réponse → JSON → DomainException traduites
avant d'empiler écriture / listes.

## Faits

1. **DTO** `PartyAccountResponse` — `publicId`, `nature`, `displayName`,
   `email` ; jamais `id` interne (`fromDomain` + `toArray`).
2. **Controller** minimal — `findByPublicId` + 404 via
   `PartyAccountNotFoundException::forPublicId` (même `errorCode`
   `party_account.not_found`).
3. **Repository** — `findByPublicId(PublicId)` ajouté (lecture Domain OK pour
   ce cas 1-entité).
4. **ExceptionListener** (Shared) — DomainException → JSON
   `{error:{code,message,context}}` traduit domain `errors` ;
   statut 404/409/400 mappé explicitement sur `errorCode()` (pas de magie
   sur le nom de classe) ; non-Domain → 500 générique + log complet
   (`exception` dans le context Monolog → `request_id` via processor).
5. **Accept-Language** → locale `en|fr|ar`, défaut `en`.
6. **X-Request-Id** — déjà posé par `RequestIdSubscriber` ; aussi forcé sur
   les réponses d'erreur du listener.

## ADR-003 (note)

`findByPublicId` via Repository Domain acceptable pour ce GET simple.
Les futurs endpoints de **liste** devront passer par DBAL direct, pas par
réhydratation Domain complète.

## Qualité

phpstan / deptrac / phpcpd / phpunit OK.
Rattrapage clôture : test 500 technique (HTTP + unit logger) — voir
`2026-07-21-first-controller-party-account-cloture.md`.
