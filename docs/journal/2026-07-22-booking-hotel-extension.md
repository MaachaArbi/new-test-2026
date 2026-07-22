# Journal — 2026-07-22 — Booking extension hôtel

## Contexte

Première extension par service : `booking_accommodation_detail` (1-1) +
`booking_hotel_room` (1-N). Renommage historique depuis
`booking_hotel_detail` (diff réouverture 20/07).

## BookingAccommodationDetail

Pattern Party `organization_identity` : PK = `booking_id`, generator
`NONE`, pas de setters. `accommodation_id` nullable (non réconcilié /
legacy). Vérification Application : `service_type_code = hotel` via
`AssertBookingServiceType` → `BookingServiceTypeMismatchException`
(réutilisable pour transport / car_rental).

## BookingHotelRoom — pourquoi pas assign/revoke

Contrairement aux assignations Party (`valid_from` / `valid_to`), le
schéma `booking_hotel_room` est une **collection simple** :

- pas de `valid_from` / `valid_to`
- pas de `deleted_at`
- `create` + `save` ajoute une ligne ; pas d'update métier dans cette vague

Ce n'est **pas un oubli** : décision structurelle du schéma (chambre =
élément de composition de la résa, pas une assignation historisée). Si un
besoin de retrait apparaît, vague séparée.

Plusieurs `AddBookingHotelRoom` sur le même booking sont autorisés (pas
d'unicité schéma).

## Migration

`Version20260722070000` : tables + `idx_booking_hotel_room_booking`.
Écart : `accommodation_id` sans `REFERENCES ref_accommodation` (ref_
non importé) — FK applicative.

## Tests

Integration : round-trip detail (accommodation_id null), rejet flight,
2 chambres coexistantes, rejet room sur transfer.

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-hotel-extension-cloture.md`.
