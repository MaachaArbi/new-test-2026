## Reprise à froid

Journal — 2026-07-21 — BookingFolder (Domain + Infrastructure).
Première tranche du module Booking : agrégat `booking_folder` uniquement. Pivot `booking` (clé composite / partitionnement) explicitement hors périmètre — prochaine vague, risque technique différent. Références :…
Première tranche du module Booking : agrégat `booking_folder` uniquement.
Pivot `booking` (clé composite / partitionnement) explicitement hors périmètre

## Origine

```
# TASK — Module Booking : BookingFolder (Domain + Infrastructure)

## Lecture obligatoire
1. reference/conceptual-models/modele-conceptuel-booking.md (section
   "Pivot et dossier", décisions #9)
2. reference/schemas/schema-booking-v1.sql, table booking_folder (lignes
   243-266) — colonnes réelles uniquement
3. Le pattern déjà validé sur PartyAccount (structure identique : BIGINT +
   public_id, pas de partitionnement ici, soft delete via deleted_at déjà
   vu sur PartyAccount)

## Portée
Uniquement booking_folder. PAS le pivot booking (prochaine vague, risque
technique différent — clé composite/partitionnement, traité séparément).

## Domain
src/Modules/Booking/Domain/Entity/BookingFolder.php
- Constructeur privé, factory create(referenceCode, partyAccountId,
  officeAccountId): self (PublicId auto-généré, cf. Shared/Domain)
- referenceCode : unique globalement (uq_booking_folder_reference_code) —
  vérification Application avant création, même pattern que
  party_account_office.office_code
- delete() (soft, deleted_at) — réutiliser le principe déjà formalisé dans
  docs/decisions/2026-07-21-soft-delete-vs-disable-party-account.md (pas de
  disable() ici, booking_folder n'a pas ce concept dans le schéma)
- Getters : id, publicId, referenceCode, partyAccountId, officeAccountId,
  deletedAt/isDeleted

## Repository
BookingFolderRepositoryInterface : findById, findByPublicId (exclut
deleted), existsByReferenceCode, save, delete (docblock précondition
identique à celui déjà utilisé pour les entités soft-deletables Party)

## Application
CreateBookingFolder/{Command,Handler} : vérifie existsByReferenceCode()
avant create() — même discipline que CreatePartyAccountGroup

## Infrastructure
Mapping Doctrine XML (booking_folder table, mapping simple, comme
PartyAccount), Repository Doctrine, dossier config/doctrine/mappings/Booking/
(nouveau, cohérent avec Party/ et Core/ déjà en place)

## Tests
Unit : create OK, delete idempotent
Integration (PostgreSQL réel) : round-trip, referenceCode dupliqué rejeté
avant SQL, soft-delete puis findByPublicId → introuvable

## Documentation
- docs/journal/2026-07-2X-booking-folder.md
- docs/STATUS.md : nouvelle section Booking — "BookingFolder fait (Domain +
  Infrastructure). Pivot booking (partitionné) à venir, sujet de la
  prochaine vague."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
