# Journal — 2026-07-22 — Règlements : HTTP

## Endpoints

| Méthode | Path | Action |
|---|---|---|
| POST | `/api/v1/reglements/instruments` | Création instrument |
| PATCH | `/api/v1/reglements/instruments/{publicId}/status` | Transition statut |
| POST | `/api/v1/reglements/instruments/{publicId}/credit` | Crédit grand livre |
| POST | `/api/v1/reglements/matchings` | Création lettrage |
| DELETE | `/api/v1/reglements/matchings/{publicId}` | Soft unmatch |
| GET | `/api/v1/party-accounts/{publicId}/reglements/balance` | Lecture solde(s) |

## Correction — IDs ledger / matching en publicId (revue)

La vague initiale exposait `debitEntryId` / `creditEntryId` / `matching.id`
en HTTP, en justifiant l'absence de GET individuel ledger. **Cet argument
ne tenait pas** : le pattern `requireXxxByPublicId()` / `findByPublicId()`
existe déjà (instruments, party-accounts) et suffit à résoudre un UUID
sans endpoint GET dédié. Correction appliquée suite à revue :

- Body matching : `debitEntryPublicId` / `creditEntryPublicId`
- Résolution publicId → id interne dans le Controller (avant Command)
- Réponse matching : `publicId` + publicIds des écritures, **pas** `id`
- DELETE `/matchings/{publicId}` (UUID)
- Balance HTTP : `lastEntryId` retiré (reste côté repo Domain si besoin)

## Classification HTTP — `reglement_instrument.not_active` → **409**

Même nature que `booking.service_type_mismatch` : l'état courant de la
ressource rend l'action impossible (instrument non Active → pas de
crédit). Aligné en **409 Conflict**, pas 422 (422 = contenu / règles de
plafond / validation métier de payload, pas « mauvais état ressource »).

## ExceptionListener

- 404 : not_found (instrument, matching, ledger, entry_type)
- 409 : status_unchanged, **not_active**
- 422 : invalid / book_mismatch / exceeds_* / currency / payment method /
  transfer

## Tests

WebTestCase + filet `assertNoForbiddenKeys` sur les 6 endpoints
(aucune clé `id` / `lastEntryId` dans les JSON).

## Clone phpcpd

`BookingHttpSupport` ↔ `ReglementsHttpSupport` — **accepté** (todo).

## Hors périmètre

Orchestration auto-matching.

## Qualité

- phpstan : OK
- deptrac : 0
- phpunit : 365 tests, 2404 assertions (2 notices préexistants)
- phpcpd : clone HttpSupport Booking↔Règlements accepté (todo)
