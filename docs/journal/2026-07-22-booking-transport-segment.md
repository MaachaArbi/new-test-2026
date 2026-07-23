## Reprise à froid

Journal — 2026-07-22 — BookingTransportSegment.
Extension 1-N multi-service : `booking_transport_segment` pour flight / train / maritime / transfer (modèle conceptuel + besoin métier transfert). Collection simple (pas d'update), comme `booking_hotel_room`.
Extension 1-N multi-service : `booking_transport_segment` pour
flight / train / maritime / transfer (modèle conceptuel + besoin métier

## Origine

```
# TASK — Module Booking : BookingTransportSegment (extension multi-service)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, table booking_transport_segment
2. reference/conceptual-models/modele-conceptuel-booking.md, section
   "Extensions par service"
3. AssertBookingServiceType.php existant (vérifie une égalité stricte —
   à étendre, pas dupliquer)

## Contrainte multi-service (nouveauté vs l'extension hôtel)
booking_transport_segment s'applique à PLUSIEURS types de service : flight,
train, maritime, transfer (confirmé par le commentaire du schéma). PAS
uniquement 'hotel' comme la vague précédente.

Étendre AssertBookingServiceType pour accepter soit un type unique (usage
existant, ne rien casser), soit une liste — décide de la forme la plus
propre (ex: __invoke(int $bookingId, array $allowedServiceTypes) avec un
seul élément pour compatibilité, ou une méthode séparée) et documente le
choix. Vérifier que SetBookingAccommodationDetailHandler et
AddBookingHotelRoomHandler continuent de fonctionner sans modification si
possible, sinon adapter proprement (pas de duplication de logique entre
l'ancien et le nouveau chemin).

BookingServiceTypeMismatchException::forBooking() devra probablement
accepter la LISTE des types attendus dans son contexte (pas un seul), pas
juste le premier qui ne matche pas — vérifier la structure existante et
l'adapter si besoin, en gardant les tests existants sur l'extension hôtel
au vert.

## Domain
src/Modules/Booking/Domain/Entity/BookingTransportSegment.php
- id (IDENTITY), bookingId, sequenceNumber (int, défaut 1), carrierCode
  (?string), departureAt/arrivalAt (DateTimeImmutable, obligatoires),
  departureLocation/arrivalLocation (?string)
- create(...) — validation : arrivalAt >= departureAt (même logique que
  Booking::create() sur startDate/endDate — miroir d'un CHECK si le schéma
  en a un, sinon règle Domain pure ajoutée par cohérence, à documenter)
- Pas d'update dans cette vague (collection simple, comme booking_hotel_room)

## Repository
BookingTransportSegmentRepositoryInterface : findByBookingId(int): array
(triée par sequence_number), save

## Application
AddBookingTransportSegment/{Command,Handler} — vérifie service_type dans
[flight, train, maritime, transfer] avant création. Appelable plusieurs
fois par booking (aller + retour + correspondances).

## Infrastructure
Mapping XML, Repository Doctrine, migration slice (table +
idx_booking_transport_segment_booking).

## Tests (PostgreSQL réel)
- Création segment simple pour un booking 'flight' → round-trip
- Rejet pour un booking 'hotel' (type non autorisé pour cette extension)
- Plusieurs segments (aller + retour) sur le même booking, triés par
  sequence_number à la lecture
- arrivalAt < departureAt → rejeté par le Domain avant SQL
- Vérifier explicitement que les tests EXISTANTS de l'extension hôtel
  (BookingHotelExtensionPersistenceTest) passent toujours sans modification
  après le changement d'AssertBookingServiceType — non-régression prioritaire

## Documentation
- docs/journal/2026-07-2X-booking-transport-segment.md — expliquer le
  choix d'extension de AssertBookingServiceType (signature, compatibilité
  ascendante)
- docs/STATUS.md
- docs/backlog/todo.md : reste car_rental_detail, charges/settlements/
  payer_split, cancellation policy, HTTP

Relance phpstan/deptrac/phpcpd/phpunit — vérifier explicitement que le
compte de tests précédent (186) ne régresse pas, seulement s'additionne.
Colle le contenu intégral de tous les fichiers créés/modifiés (y compris
AssertBookingServiceType.php modifié) et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — BookingTransportSegment

## Contexte

Extension 1-N multi-service : `booking_transport_segment` pour
flight / train / maritime / transfer (modèle conceptuel + besoin métier
transfert). Collection simple (pas d'update), comme `booking_hotel_room`.

## AssertBookingServiceType — extension signature

**Choix : `__invoke(int $bookingId, string|array $allowedServiceTypes)`**

- `string` : un seul type — **compat ascendante** : les handlers hôtel
  (`SetBookingAccommodationDetailHandler`, `AddBookingHotelRoomHandler`)
  appellent toujours avec `'hotel'` sans modification.
- `array` : liste autorisée — usage transport
  `['flight','train','maritime','transfer']`.

Un seul chemin de code (`in_array`), pas de méthode parallèle à maintenir.

## BookingServiceTypeMismatchException

`forBooking()` accepte `string|array`. Contexte :

- toujours `expected_service_types` (liste)
- `expected_service_type` (singulier) **uniquement** si un seul type attendu
  — conserve les assertions des tests hôtel existants

## Règle Domain dates

Pas de CHECK SQL sur arrival/departure. Domain : `arrivalAt >= departureAt`
(miroir de `Booking::create` start/end) →
`InvalidBookingTransportSegmentException`.

## Tests

Integration transport + Unit dates ; non-régression
`BookingHotelExtensionPersistenceTest` (handlers hôtel inchangés).

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-transport-segment-cloture.md`.
