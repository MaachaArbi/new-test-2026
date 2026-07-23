## Reprise à froid

Journal — 2026-07-22 — Clôture Booking montants / devises / Money.
Vague montants/devises du pivot `booking` **close et validée** (Shared Domain + Booking Domain/Application/Infrastructure + migration + tests). Détail livré : `2026-07-22-booking-money-fields.md`. Hors périmètre…
Vague montants/devises du pivot `booking` **close et validée**
(Shared Domain + Booking Domain/Application/Infrastructure + migration +

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Clôture Booking montants / devises / Money

## Contexte

Vague montants/devises du pivot `booking` **close et validée**
(Shared Domain + Booking Domain/Application/Infrastructure + migration +
tests). Détail livré : `2026-07-22-booking-money-fields.md`.

Hors périmètre confirmé : `booking_charge` / `booking_settlement`
(décisions conceptuelles #5/#6), `price_breakdown` JSONB (non mappé Domain).

## Périmètre clos

- Shared : `Money`, `ExchangeRate` ; exceptions `CurrencyMismatchException`,
  `InvalidCurrencyCodeException`, `InvalidExchangeRateException`
- Booking Domain : champs montants/devises/taux/`PaymentStatus` sur
  `Booking::create()` (paramètres explicites, cohérence devise contrôlée)
- `PaymentStatus` enum PHP (`unpaid`/`partial`/`paid`) — pas
  `OpenReferentialCode`
- Mapping : montants `bigint` + devises `string` séparés ; getters
  reconstruisent `Money` ; Type Doctrine `exchange_rate` 1:1 ; enum
  `payment_status`
- Application : `CreateBookingCommand` / `Handler` étendus
- Migration `Version20260722060000` : `DROP DEFAULT` sur
  `achat`/`vente_currency_code`
- Traductions `money.*` / `exchange_rate.invalid_format` (en/fr/ar)
- Tests Unit + Integration (round-trip précision montants/taux)

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 161 tests, 897 assertions

**phpstan** (exit 0) — No errors

**deptrac** (exit 0) — Violations 0 · Allowed 394 · Uncovered 190

**phpcpd** (exit 0) — No clones found (seuil 10 / 20)
