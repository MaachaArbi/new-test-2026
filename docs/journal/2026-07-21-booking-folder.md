# Journal — 2026-07-21 — BookingFolder (Domain + Infrastructure)

## Contexte

Première tranche du module Booking : agrégat `booking_folder` uniquement.
Pivot `booking` (clé composite / partitionnement) explicitement hors périmètre
— prochaine vague, risque technique différent.

Références : modèle conceptuel Booking (« Pivot et dossier »),
`schema-booking-v1.sql` L243-266, soft-delete formalisé dans
`docs/decisions/2026-07-21-soft-delete-vs-disable-party-account.md`
(pas de `disable()` : le schéma n'a pas `is_disabled`).

## Faits

1. **Domain** : `BookingFolder` (factory `create`, soft-delete idempotent),
   `BookingFolderRepositoryInterface`, exception
   `BookingFolderReferenceCodeAlreadyUsedException`
2. **Application** : `CreateBookingFolder` — `existsByReferenceCode()` avant
   `create()` (même discipline que `CreatePartyAccountGroup` / office_code)
3. **Infrastructure** : mapping XML `config/doctrine/mappings/Booking/`,
   `DoctrineBookingFolderRepository`, alias DI
4. **Schéma** : migration `Version20260721210000` — slice `booking_folder`
   uniquement (pas l'import complet `schema-booking-v1.sql`, dépendances
   ref_/pointvente non encore en base)
5. **Tests** : Unit create + delete idempotent ; Integration round-trip,
   doublon reference_code rejeté avant SQL, soft-delete → `findByPublicId`
   introuvable

## Hors périmètre

Pivot `booking` partitionné, extensions service, HTTP Booking.
