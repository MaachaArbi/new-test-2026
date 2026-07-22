# Journal — 2026-07-22 — Référentiel data-driven booking_service_type_extension

## Contexte

Retour du chat DB architect (**Volet A**) : les listes PHP figées du type
`ALLOWED_SERVICE_TYPES = ['flight','train',…]` ne doivent plus piloter
quelles extensions (accommodation / transport_segment / car_rental) sont
autorisées pour un `service_type`. La source de vérité est un référentiel
N-N en base :

- `booking_service_extension` (code, label) — catalogue des extensions
- `booking_service_type_extension` (service_type_code, extension_code) —
  mapping autorisé

Seed initial : accommodation←hotel ; transport_segment←flight/train/
maritime/transfer ; car_rental←car_rental (3 + 6 lignes).

## Changement de philosophie

Avant : le Handler connaît la liste des `service_type` autorisés et la
passe à `AssertBookingServiceType`. Ajouter `bus` = redeploy PHP.

Après : le Handler ne connaît que le **code d'extension** qu'il implémente
(`accommodation`, `transport_segment`). L'Assert lit le mapping en DB
(DBAL, ADR-003, pas d'entité Domain pour une simple existence). Ajouter
`bus → transport_segment` = un INSERT SQL, zéro PHP.

## Livré

- Migration `Version20260722100000` (+ DDL aligné dans
  `reference/schemas/schema-booking-v1.sql`)
- `AssertBookingServiceType::__invoke(int $bookingId, string $extensionCode)`
- Exception : contexte `extension_code` + `actual_service_type` (plus
  `expected_service_types`)
- Handlers hôtel → `'accommodation'` ; transport → `'transport_segment'`
  (constante `ALLOWED_SERVICE_TYPES` supprimée)
- Test data-driven : service_type **`bus`** (seedé dans
  `booking_service_type` mais non mappé au seed initial) — rejeté, puis
  débloqué par INSERT SQL seul

## Prochaine action

car_rental_detail (pourra réutiliser Assert avec `'car_rental'`).
