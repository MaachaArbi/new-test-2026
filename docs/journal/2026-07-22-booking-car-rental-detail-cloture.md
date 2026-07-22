# Journal — 2026-07-22 — Clôture BookingCarRentalDetail

## Contexte

`BookingCarRentalDetail` **clos et validé** (Domain + Application +
Infra + Assert data-driven `car_rental` + tests). Détail livré :
`2026-07-22-booking-car-rental-detail.md`.

Les 3 extensions par service (hôtel, transport, car_rental) sont
désormais faites, toutes pilotées par `booking_service_type_extension`.

## Périmètre clos

- Domain : `BookingCarRentalDetail` (PK=`booking_id`, generator NONE) ;
  validation optionnelle `dropoffAt >= pickupAt` ;
  `InvalidBookingCarRentalDetailException`
- Application : `SetBookingCarRentalDetail{Command,Handler}` ;
  Assert `extension_code = 'car_rental'` (mapping déjà seedé)
- Infra : XML (`datetimetz_immutable`) + repo Doctrine ;
  migration `Version20260722110000` + trigger `updated_at`
- Traduction `booking_car_rental_detail.invalid_dates` (en/fr/ar)
- Tests Unit + Integration : round-trip précision horaire, rejet hotel,
  dates Domain, `dropoff_location` null

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 200 tests, 1120 assertions (193 → 200, pas de régression)

**phpstan** (exit 0) — No errors

**deptrac** (exit 0) — Violations 0 · Allowed 464 · Uncovered 196

**phpcpd** (exit 0) — No clones found (seuil 10 / 20)
