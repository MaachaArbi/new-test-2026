# Journal — 2026-07-21 — Clôture premier Controller + ExceptionListener

## Contexte

`GET /api/v1/party-accounts/{publicId}` + `ExceptionListener` JSON global sont
**clos et validés**. Détail livré :
`2026-07-21-first-controller-party-account.md`.

## Périmètre clos

- DTO réponse `PartyAccountResponse` (publicId / nature / displayName / email ;
  jamais `id` interne)
- Controller minimal + `findByPublicId` + `PartyAccountNotFoundException::forPublicId`
  (même `errorCode` que `forId` — pas d'ajout catalogue errors)
- `ExceptionListener` : DomainException → JSON traduit domain `errors` ;
  404 / 409 / 400 mappés sur `errorCode()` ; non-Domain → 500 générique
  sans fuite + log complet ; `X-Request-Id` présent
- Tests HTTP : 200 sans `id`, 404 fr/en, **500 technique sans fuite**
  (repo stub) ; test unit listener (logger mocké)
- ADR-003 noté : listes futures → DBAL direct

## Corrections / rattrapage de clôture

1. Test manquant exception technique → 500 générique (HTTP + unit logger)
2. Stubs PHPUnit 13 (`createStub`) à la place de mocks sans expectation

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 93 tests, 481 assertions

**phpstan** (exit 0) — No errors (`--memory-limit=512M`)

**deptrac** (exit 0) — Violations 0 · Allowed 196 · Uncovered 85

**phpcpd** (exit 0) — No clones found (seuil 10 / 20)
