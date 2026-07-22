# Journal — 2026-07-22 — Booking pivot : montants, devises, Money VO

## Contexte

Vague montants/devises du pivot `booking` (hors `booking_charge` /
`booking_settlement` — décisions conceptuelles #5/#6, hors périmètre).
Colonnes : devises/taux achat·vente, totaux, marges, `paid_amount`,
`payment_status`.

## Fondation Shared

- `Money` : unités mineures brutes (`int`) + code devise 3 lettres.
  **Pas** de résolution `ref_currency.minor_unit` — conversion affichage
  (centimes → unité majeure) hors périmètre de cette vague.
- `Money::add()` refuse les devises différentes (`CurrencyMismatchException`).
- `ExchangeRate` : string décimale (jamais `float`), format NUMERIC(14,6).
- Exceptions : `CurrencyMismatchException`, `InvalidCurrencyCodeException`,
  `InvalidExchangeRateException`.

## PaymentStatus

Enum PHP natif (`unpaid` / `partial` / `paid`) — CHECK SQL fermé, **pas**
`OpenReferentialCode` (contrairement aux codes référentiels ouverts).

## Mapping Money — choix retenu

Money porte **deux** infos (amount + currency) alors que le SQL les sépare
(`total_achat_amount` BIGINT + `achat_currency_code` VARCHAR partagé).

**Pas** de Type Doctrine composite (encoderait mal une colonne, ou
dupliquerait la devise sur chaque montant).

Retenu :

1. Propriétés Domain internes : `int` pour chaque montant + `string` pour
   `achatCurrencyCode` / `venteCurrencyCode`.
2. Mapping XML : colonnes séparées (`bigint` / `string` / `exchange_rate`).
3. Getters Domain `totalAchatAmount(): Money` etc. reconstruisent le VO
   à la lecture ; `create()` accepte `Money` et extrait `amount()` après
   contrôle de cohérence devise.

`ExchangeRate` reste un Type Doctrine 1:1 colonne (`exchange_rate`), comme
`Email` / `PublicId`.

## Migration

`Version20260722060000` : `DROP DEFAULT` sur `achat_currency_code` /
`vente_currency_code` (DEFAULT `'TND'` temporaire de la slice initiale
retiré — le Domain fournit toujours une valeur).

## Tests

- Unit : `MoneyTest`, `ExchangeRateTest`, `PaymentStatusTest`, `BookingTest`
- Integration : round-trip précision montants/taux (`BookingPersistenceTest`)

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-money-fields-cloture.md`.
