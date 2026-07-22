# Journal — 2026-07-22 — BookingSettlement

## Contexte

`booking_settlement` : faits de répartition par bénéficiaire (fournisseur /
agence principale / distributeur). Historisé (assign / revoke). **Aucun**
recalcul des totaux ou marges `Booking` (frontière nette avec
`BookingCharge` + `recalculateTotals()`).

`resale_price_amount` : purement informatif (revente hors périmètre
financier de ce booking) — aucune agrégation Domain/Application.

## Choix VO pour `rate` (NUMERIC(6,3))

**Pas** de duplication de la regex `ExchangeRate` (14,6).

- Nouveau helper Shared `NumericDecimal::isValidPositive($value, $precision, $scale)`
  — règle générique NUMERIC(p,s), string jamais float.
- `ExchangeRate` refactoré pour déléguer à `NumericDecimal(14, 6)`.
- Nouveau VO Booking `SettlementRate` via `NumericDecimal(6, 3)` + type
  Doctrine `settlement_rate`.

Sémantique distincte (taux de change vs taux commission settlement) → deux
VO ; validation partagée → pas de copier-coller de pattern.

## Repository

- `findByBookingId(int $bookingId, bool $activeOnly = true)` — **actifs
  uniquement par défaut** (`valid_to IS NULL`) ; `activeOnly: false` pour
  l’historique.
- `hasActiveSettlement(bookingId, role, accountId)` — DBAL `SELECT 1`
  (ADR-003), miroir `uq_booking_settlement_active`.
- `assign` / `revoke` via `UnitOfWork` (persist ; commit = Handler).

## Application

- `AssignBookingSettlementHandler` : `hasActiveSettlement` avant assign,
  un seul `commit()`. **N’injecte pas** `BookingRepository`.
- `RevokeBookingSettlementHandler` : find → Domain `revoke()` → commit.

## Tests

Integration PostgreSQL : round-trip, doublon actif, rôles distincts
coexistants, revoke + réassign, non-mutation totaux Booking, absence de
dépendance BookingRepository, resale non agrégé vers booking.

## Qualité

Voir sortie des 4 outils en fin de vague.
