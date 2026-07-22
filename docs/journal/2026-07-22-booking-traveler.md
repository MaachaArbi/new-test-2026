# Journal — 2026-07-22 — BookingTraveler (snapshot voyageur)

## Contexte

Agrégat `booking_traveler` : snapshot figé rattaché directement à un
booking (pas de N-N). Création + lecture uniquement — pas d'update
(nature snapshot ; correction = vague séparée si besoin).

## Choix Domain

- `email` : **string** déclaratif, pas `Shared\Email` (saisie libre figée,
  pas une identité de compte vérifiée).
- `age` et `birth_date` coexistent (besoin métier hôtel vs vol/maritime —
  confirmé schéma).
- `party_account_id` / `hotel_room_id` nullable (saisie libre / hors hôtel).
- Pays : bigint simples (`ref_country` non modélisé Domain).

## Contrainte pax leader

Index partiel `uq_booking_traveler_pax_leader` (un seul `is_pax_leader`
par `booking_id`). Application : `hasActivePaxLeader()` avant create si
`isPaxLeader=true` → `BookingTravelerPaxLeaderAlreadySetException`
(refus avant SQL). Contrainte **par booking**, pas globale.

## Infrastructure

Table non partitionnée, PK IDENTITY. `booking_id` bigint applicatif.
Migration `Version20260722080000` + FK SQL vers `booking_hotel_room` /
`party_account` (tables présentes).

## Tests

Round-trip complet ; 2 voyageurs sans leader ; 2ᵉ leader rejeté ; leaders
indépendants sur 2 bookings ; age+birthDate simultanés.

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-traveler-cloture.md`.
