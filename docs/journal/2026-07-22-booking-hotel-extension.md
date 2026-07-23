## Reprise à froid

Journal — 2026-07-22 — Booking extension hôtel.
Première extension par service : `booking_accommodation_detail` (1-1) + `booking_hotel_room` (1-N). Renommage historique depuis `booking_hotel_detail` (diff réouverture 20/07).
Première extension par service : `booking_accommodation_detail` (1-1) +
`booking_hotel_room` (1-N). Renommage historique depuis

## Origine

```
# TASK — Module Booking : extension hôtel (BookingAccommodationDetail + BookingHotelRoom)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, tables booking_accommodation_detail
   et booking_hotel_room (chercher aussi diff-booking-reouverture-20-07.diff
   pour le renommage depuis booking_hotel_detail)
2. Booking.php existant (serviceTypeCode déjà disponible pour vérification)
3. Pattern déjà validé : PartyAccountOrganizationIdentity (extension 1-1,
   PK=FK, generator NONE) pour BookingAccommodationDetail

## Règle métier explicite (tirée du schéma, pas inventée)
Ces deux tables n'ont de sens QUE pour un booking dont serviceTypeCode =
'hotel'. Vérification Application avant création (comme
PartyAccountMustBeOrganizationException) — créer
BookingServiceTypeMismatchException si elle n'existe pas déjà, réutilisable
pour les futures extensions (transport, car_rental) qui auront la même
contrainte avec d'autres valeurs.

## 1. BookingAccommodationDetail (extension 1-1)
src/Modules/Booking/Domain/Entity/BookingAccommodationDetail.php
- PK = bookingId (pas de generator, comme organization_identity)
- create(bookingId, ?accommodationId, ?accommodationNameSnapshot, ?boardType)
  — tous nullable sauf bookingId (cf. schéma : accommodation_id nullable,
  résa non réconciliée au référentiel)
- Pas de mutation dans cette vague (comme organization_identity — si un
  besoin de correction apparaît, vague séparée)

Repository : findByBookingId, save

## 2. BookingHotelRoom (collection 1-N, NOUVEAU pattern)
src/Modules/Booking/Domain/Entity/BookingHotelRoom.php
- id généré (IDENTITY, PK simple — pas de partitionnement ici, cette table
  n'est pas partitionnée contrairement à booking)
- create(bookingId, ?roomType)
- PAS d'historisation (contrairement aux assignations Party) : c'est une
  collection simple, pas de valid_from/valid_to dans le schéma. Une chambre
  ajoutée reste ajoutée ; si un besoin de suppression apparaît, vague
  séparée (le schéma ne prévoit ni deleted_at ni mécanisme de retrait ici)

Repository : findByBookingId(bookingId): array (liste, pas un seul), save
(ajoute une nouvelle ligne, jamais d'update)

## 3. Application
Deux Handlers séparés (SetBookingAccommodationDetail, AddBookingHotelRoom)
— vérifient serviceTypeCode='hotel' avant d'agir (charger le Booking via
BookingRepositoryInterface existant). AddBookingHotelRoom peut être appelé
plusieurs fois pour le même booking (plusieurs chambres) — pas de contrainte
d'unicité à vérifier, le schéma n'en prévoit aucune.

## 4. Infrastructure
Mapping Doctrine XML pour les deux (nouveau dossier ou existant
Booking/), Repository Doctrine. FK applicative vers booking.id — PAS de FK
SQL réelle dans le mapping (cohérent avec la note du schéma sur le
partitionnement), juste un bigint simple.

## 5. Migration
Nouvelle migration slice : les deux tables + leurs index (idx_booking_hotel_room_booking).

## Tests (PostgreSQL réel)
- Création accommodation_detail pour un booking hotel → OK, round-trip
- Création sur un booking non-hotel (ex: service_type='flight') → rejetée
  avant SQL
- Ajout de plusieurs chambres au même booking → toutes présentes, aucune
  ne remplace l'autre (test explicite : 2 chambres créées, 2 chambres
  retrouvées via findByBookingId)
- accommodation_id nullable accepté (cas "non réconcilié")

## Documentation
- docs/journal/2026-07-2X-booking-hotel-extension.md — expliquer
  explicitement pourquoi BookingHotelRoom n'a pas le pattern
  assign/revoke des assignations Party (pas d'historisation dans le
  schéma, décision structurelle pas un oubli)
- docs/STATUS.md : "Booking : première extension par service (hôtel)
  faite. Reste : autres services (transport, car_rental), charges/
  settlements/traveler, cancellation policy, HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
