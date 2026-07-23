## Reprise à froid

Journal — 2026-07-22 — BookingTraveler (snapshot voyageur).
Agrégat `booking_traveler` : snapshot figé rattaché directement à un booking (pas de N-N). Création + lecture uniquement — pas d'update (nature snapshot ; correction = vague séparée si besoin).
Agrégat `booking_traveler` : snapshot figé rattaché directement à un
booking (pas de N-N). Création + lecture uniquement — pas d'update

## Origine

```
# TASK — Module Booking : BookingTraveler (snapshot voyageur)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, table booking_traveler (toutes
   les colonnes, le commentaire "snapshot figé", uq_booking_traveler_pax_leader)
2. reference/conceptual-models/modele-conceptuel-booking.md, section
   "Voyageur"
3. BookingHotelRoom.php existant (hotel_room_id référence cette table,
   pas de FK SQL réelle nécessaire côté mapping puisque booking_hotel_room
   n'est pas partitionnée — FK Doctrine simple possible ici, à la
   différence de booking_id)

## Portée
Un agrégat simple : création + lecture uniquement dans cette vague (pas
de update — c'est un snapshot figé par nature, une correction éventuelle
serait un sujet séparé si un besoin réel apparaît). PAS de HTTP.

## Domain
src/Modules/Booking/Domain/Entity/BookingTraveler.php
- id (IDENTITY), bookingId (int, FK applicative), hotelRoomId (?int),
  partyAccountId (?int)
- firstName/lastName (string, obligatoires), civility/phone/email
  (nullable, strings simples — PAS de VO Email ici : c'est un champ
  déclaratif libre sur un snapshot figé, pas une donnée de compte vérifiée,
  ne pas réutiliser Shared\Email qui imposerait une validation stricte non
  demandée par le schéma)
- age (?int), birthDate (?DateTimeImmutable), birthPlace (?string)
- nationalityCountryId/residenceCountryId (?int — ref_country non
  modélisé Domain, bigint simple)
- documentType/documentNumber/drivingLicenseNumber (nullable strings)
- isPaxLeader (bool, défaut false)
- ticketNumber/pnr/travelClass (nullable strings)
- create(...) — factory unique, tous les champs nullable sauf bookingId/
  firstName/lastName (cohérent avec NOT NULL du schéma)

## Repository
BookingTravelerRepositoryInterface :
- findByBookingId(int $bookingId): array (liste)
- hasActivePaxLeader(int $bookingId): bool — vérifie s'il existe déjà un
  is_pax_leader=true pour ce booking
- save(BookingTraveler $traveler): void

## Application
CreateBookingTraveler/{Command,Handler} :
- Si $command->isPaxLeader === true, vérifie hasActivePaxLeader() AVANT
  create() — si déjà un pax leader, lève BookingTravelerPaxLeaderAlreadySetException
  (nouvelle exception, errorCode dédié, contexte booking_id)
- Sinon, création directe

## Infrastructure
Mapping Doctrine XML (booking_traveler, pas de partitionnement — table
simple), Repository Doctrine, nouvelle migration slice.

## Tests (PostgreSQL réel)
- Création voyageur simple (sans pax leader) → round-trip complet, tous
  les champs vérifiés
- Deux voyageurs sur le même booking, aucun pax leader → OK, coexistent
- Premier voyageur avec isPaxLeader=true → OK
- Second voyageur avec isPaxLeader=true sur le MÊME booking → rejeté
  AVANT SQL (vérifier qu'aucune ligne en trop n'est créée)
- Un booking peut avoir un pax leader ET un autre booking peut aussi
  avoir SON PROPRE pax leader (la contrainte est par booking, pas
  globale) — test explicite de non-collision inter-booking
- party_account_id nullable accepté (cas "saisie libre")
- age ET birthDate tous deux renseignés simultanément (cas réel confirmé
  par le schéma, pas une redondance à éviter)

## Documentation
- docs/journal/2026-07-2X-booking-traveler.md
- docs/STATUS.md : "Booking : BookingTraveler (snapshot) fait — création
  + contrainte pax leader unique par booking vérifiée. Reste : extensions
  transport/car_rental, charges/settlements/payer_split, cancellation
  policy, HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
