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
