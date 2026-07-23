## Reprise à froid

Journal — 2026-07-22 — BookingCarRentalDetail.
Troisième extension par service (après hôtel / transport_segment). Table 1-1 `booking_car_rental_detail` : PK = `booking_id`, horodatages `pickup_at` / `dropoff_at` en TIMESTAMPTZ (précision horaire métier — durée…
Troisième extension par service (après hôtel / transport_segment). Table
1-1 `booking_car_rental_detail` : PK = `booking_id`, horodatages

## Origine

```
# TASK — Module Booking : BookingCarRentalDetail (extension 1-1)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, table booking_car_rental_detail
2. BookingAccommodationDetail.php existant (pattern extension 1-1 déjà
   validé — répliquer, PK=bookingId, generator NONE)
3. AssertBookingServiceType.php (data-driven, extension_code = 'car_rental',
   déjà mappée dans booking_service_type_extension)

## Domain
src/Modules/Booking/Domain/Entity/BookingCarRentalDetail.php
- PK = bookingId (pas de generator, comme accommodation_detail)
- create(bookingId, ?vehicleCategory, ?vehicleBrandModel, ?pickupAt,
  ?dropoffAt, ?pickupLocation, ?dropoffLocation) — tous nullable sauf
  bookingId (cf. schéma)
- pickupAt/dropoffAt : DateTimeImmutable (PAS de string Y-m-d comme
  bookingDate — ici la précision horaire est le point, garder le type
  complet)
- Validation optionnelle : si les deux sont fournis, dropoffAt >= pickupAt
  (même logique que les autres validations de dates déjà en place) — sinon
  lever une exception dédiée

## Repository
BookingCarRentalDetailRepositoryInterface : findByBookingId, save

## Application
SetBookingCarRentalDetail/{Command,Handler} — vérifie extension_code=
'car_rental' via AssertBookingServiceType avant création (déjà mappé en
base, rien à ajouter côté référentiel)

## Infrastructure
Mapping Doctrine XML (pickupAt/dropoffAt en datetimetz_immutable, comme
assignedAt sur Booking), Repository Doctrine, migration slice.

## Tests (PostgreSQL réel)
- Round-trip complet, tous les champs
- Rejet pour un booking non car_rental (ex: hotel)
- dropoffAt < pickupAt → rejeté par le Domain avant SQL
- dropoffLocation null accepté (cas "même lieu que pickup", cf. commentaire
  schéma)
- Précision horaire vérifiée explicitement au round-trip (pas juste la
  date, l'heure aussi — ex: 15:00 doit revenir 15:00, pas minuit)

## Documentation
- docs/journal/2026-07-2X-booking-car-rental-detail.md
- docs/STATUS.md : "Booking : les 3 extensions par service (hôtel,
  transport, car_rental) sont faites, toutes data-driven. Reste : pan
  financier (différé), annulation, HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — BookingCarRentalDetail

## Contexte

Troisième extension par service (après hôtel / transport_segment). Table
1-1 `booking_car_rental_detail` : PK = `booking_id`, horodatages
`pickup_at` / `dropoff_at` en TIMESTAMPTZ (précision horaire métier —
durée calculée à l'heure près).

Guard via `AssertBookingServiceType(..., 'car_rental')` — mapping déjà
seedé dans `booking_service_type_extension`, rien à ajouter côté référentiel.

## Livré

- Domain : `BookingCarRentalDetail::create` ; validation optionnelle
  dropoff >= pickup → `InvalidBookingCarRentalDetailException`
- Application : `SetBookingCarRentalDetail{Command,Handler}`
- Infra : XML Doctrine (`datetimetz_immutable`) ; repo ; migration
  `Version20260722110000`
- Traductions en/fr/ar `booking_car_rental_detail.invalid_dates`
- Tests Unit + Integration (round-trip horaire, mismatch hotel,
  dates invalides Domain, dropoff_location null)

## Suite

Pan financier (charges/settlements/payer_split — différé), annulation, HTTP.
