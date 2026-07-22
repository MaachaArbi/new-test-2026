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
