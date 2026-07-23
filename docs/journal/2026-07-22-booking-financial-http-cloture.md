## Reprise à froid

Journal — 2026-07-22 — Clôture HTTP pan financier Booking.
Vague HTTP charges / settlements / payer-splits **close et validée** (3 POST, ExceptionListener, WebTestCase × 3). Détail livré : `2026-07-22-booking-financial-http.md`. Handlers Application **inchangés**…
Vague HTTP charges / settlements / payer-splits **close et validée**
(3 POST, ExceptionListener, WebTestCase × 3). Détail livré :

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Clôture HTTP pan financier Booking

## Contexte

Vague HTTP charges / settlements / payer-splits **close et validée**
(3 POST, ExceptionListener, WebTestCase × 3). Détail livré :
`2026-07-22-booking-financial-http.md`.

Handlers Application **inchangés** (`AddBookingCharge`,
`AssignBookingSettlement`, `AssignBookingPayerSplit`) — exposition HTTP
uniquement.

Hors périmètre confirmé : revoke HTTP settlement / payer-split ;
`booking_payment` ; B15–B18 / C3 ADR-003.

## Périmètre clos

- `POST /api/v1/bookings/{publicId}/charges` → 201 ; Money en champs plats ;
  `metadata` JSON libre ; réponse sans `id` / `bookingId`
- `POST /api/v1/bookings/{publicId}/settlements` → 201 ;
  `beneficiaryRole` via `Assert\Choice` ; `rate` laissée au VO
- `POST /api/v1/bookings/{publicId}/payer-splits` → 201
- Pattern : `BookingHttpSupport` + DTO Symfony (réf. cancellation tier)
- `ExceptionListener` : AlreadyActive → 409 ; mismatches / plafond /
  devise / taux → 422 (`exceeds_total` tranché 422, pas 409)
- Tests HTTP : 201, 404, ≥2 erreurs métier / endpoint, 422 malformé, 401 ;
  payer-splits : dépassement total jusqu'au contexte JSON

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 324 tests, 1900 assertions (2 notices)

**phpstan** (exit 0) — No errors

**deptrac** (exit 0) — Violations 0 · Allowed 685 · Uncovered 488

**phpcpd** (exit 0) — 2 clones Settlement↔PayerSplit (43 lignes) —
**acceptés** (cf. `docs/backlog/todo.md`, extraction reportée)
