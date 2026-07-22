# Journal — 2026-07-22 — Booking : transitions status_code

## Contexte

Première mutation sur `statusCode` de l'agrégat `Booking`. Référentiel
`booking_status` : draft / on_option / confirmed / completed / cancelled /
no_show (`is_final` informatif côté seed SQL).

## Règles Domain (confirmées)

- **Pas de matrice** : tout statut → tout autre, y compris final → non-final
  (réouverture métier). `is_final` non appliqué en Domain.
- **Seule règle** : transition vers le statut déjà actuel →
  `BookingStatusUnchangedException` (`booking.status_unchanged`) — pas de
  no-op silencieux. Aligné sur `PartyAccountRoleAssignment::revoke()`
  (refus si déjà fait), **pas** sur `PartyAccount::delete()` (idempotent).

## Application — Handler dédié

**Retenu : `TransitionBookingStatusHandler`** (séparé de
`UpdateBookingWorkflow`).

Pourquoi pas étendre le Handler workflow flags :

- `status_code` est **le** champ structurant du cycle de vie, pas un
  booléen / label.
- Point d'extension propre pour effets de bord futurs (ex. notifier le
  fournisseur au passage à `confirmed`) sans polluer le PATCH flags.
- API HTTP future : endpoint/commande distincts plus clairs.

## Tests

- Unit : différent OK ; même statut → exception ; `completed` → `draft` OK
  (prouve l'absence de matrice)
- Integration : round-trip + `BookingNotFoundException` id inconnu

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-status-transitions-cloture.md`.
