## Reprise à froid

Journal — 2026-07-22 — Clôture Booking workflow flags.
Mutations workflow du pivot `booking` **closes et validées** (Domain + Application Handler unique + mapping XML + tests no_changes). Détail livré : `2026-07-22-booking-workflow.md`. Hors périmètre confirmé :…
Mutations workflow du pivot `booking` **closes et validées** (Domain +
Application Handler unique + mapping XML + tests no_changes). Détail

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Clôture Booking workflow flags

## Contexte

Mutations workflow du pivot `booking` **closes et validées** (Domain +
Application Handler unique + mapping XML + tests no_changes). Détail
livré : `2026-07-22-booking-workflow.md`.

Hors périmètre confirmé : transitions `status_code` ;
`booking_on_request_flag` (1-N).

## Périmètre clos

- Domain : `markAsOnRequest` / `clearOnRequest`, `assignToAgent` /
  `unassign` (réassignation sans erreur), `lock` / `unlock`,
  `markAsDisputed` / `clearDispute`, `updateSupplierStatusLabel`
- Application : `UpdateBookingWorkflowCommand` / `Handler` (PATCH partiel
  `has*` — aligné PartyAccount) ; `BookingNotFoundException` /
  `BookingNoChangesException`
- Mapping XML : colonnes workflow ajoutées (`is_on_request`, assignation,
  lock, dispute, `supplier_status_label`)
- Choix documenté : `hasX=true` + valeur `null` (hors assignment / label)
  = pas de changement, pas erreur malformée
- Décision #3 laissée ouverte (pas de contrainte croisée
  `is_on_request` ↔ `status_code`)
- Traductions `booking.not_found` / `booking.no_changes_provided`
  (en/fr/ar)
- Tests Unit Domain + Handler no_changes + Integration round-trip

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 172 tests, 952 assertions

**phpstan** (exit 0) — No errors

**deptrac** (exit 0) — Violations 0 · Allowed 400 · Uncovered 190

**phpcpd** (exit 0) — No clones found (seuil 10 / 20)
