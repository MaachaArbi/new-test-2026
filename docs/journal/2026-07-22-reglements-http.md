## Reprise à froid

Journal — 2026-07-22 — Règlements : HTTP.
La vague initiale exposait `debitEntryId` / `creditEntryId` / `matching.id`
en HTTP, en justifiant l'absence de GET individuel ledger. **Cet argument
ne tenait pas** : le pattern `requireXxxByPublicId()` / `findByPublicId()`

## Origine

```
# TASK — HTTP Règlements : instrument, transition, crédit, matching, lecture solde

## Lecture obligatoire
1. BookingHttpSupport.php + AddBookingCancellationTierController.php
   (pattern déjà établi à répliquer)
2. Tous les Handlers Règlements déjà validés (Create/TransitionInstrument,
   PostCreditFromInstrument, CreateMatching, UnmatchMatching)
3. ReglementBalanceRepositoryInterface (lecture seule)

## Portée — 6 endpoints
1. POST /api/v1/reglements/instruments — création
2. PATCH /api/v1/reglements/instruments/{publicId}/status — transition
3. POST /api/v1/reglements/instruments/{publicId}/credit — poste le
   crédit grand livre depuis cet instrument (résout publicId→instrumentId)
4. POST /api/v1/reglements/matchings — création (debitEntryId/
   creditEntryId en body, ce sont des ID internes de ledger_entry — PAS de
   publicId exposé sur ReglementLedgerEntry pour l'instant puisqu'aucun
   GET individuel n'existe dessus ; décide si c'est acceptable d'exposer
   ces ID en input ou si un mécanisme différent est nécessaire, documente
   ton choix)
5. DELETE /api/v1/reglements/matchings/{id} — unmatch (soft)
6. GET /api/v1/party-accounts/{publicId}/reglements/balance — lecture
   solde(s), query param optionnel partyRole/currencyCode, sinon retourne
   findAllBalancesForParty()

## Nuances par endpoint
- Instrument : DTO requête/réponse standard, PAS d'id interne exposé sauf
  publicId (comme Booking). Transition : body {status, reason?}, valider
  via Assert\Choice sur ReglementInstrumentStatus::cases()
- Credit : pas de body, juste l'action. 201, réponse = infos de l'écriture
  créée (SANS id interne, mais avec public_id si ReglementLedgerEntry en
  a un — vérifier, l'entité l'a côté Domain)
- Matching : InstrumentPartyRole validé si présent, gestion des 4
  exceptions déjà existantes (NotFound ×2, BookMismatch, ExceedsCredit,
  ExceedsDebit) — mapper chacune correctement dans ExceptionListener
  (404/422/422 respectivement, cohérent avec les conventions déjà établies)
- Balance : endpoint de LECTURE pure, GET simple, pas de body

## Erreurs — vérifier/compléter ExceptionListener pour TOUTES les
   exceptions Règlements introduites depuis le début du module (instrument,
   ledger, matching) — grep systématique plutôt que de se fier à la mémoire

## Tests (WebTestCase, PostgreSQL réel), pour chacun des 6 endpoints
Cas valide, 404 si applicable, au moins une erreur métier propre, input
malformé → 422, sans JWT → 401. Un test de bout en bout supplémentaire :
créer instrument → poster crédit → créer un matching contre une obligation
préexistante → lire le solde via GET → vérifier la cohérence complète à
travers la chaîne HTTP (pas juste Application comme le test déjà fait).

## Documentation
- docs/journal/2026-07-2X-reglements-http.md
- docs/STATUS.md : "Règlements : HTTP complet sur instrument/transition/
  crédit/matching/solde. Reste : orchestration auto-matching."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Remonte en texte brut, blocs de
100 lignes maximum par message, un fichier par bloc, numérotés dans
l'ordre — même règle que la vague précédente, ça a fini par bien
fonctionner une fois stabilisé.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
