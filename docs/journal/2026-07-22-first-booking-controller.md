# Journal — 2026-07-22 — Premier Controller Booking (GET public_id)

## Contexte

Premier endpoint HTTP Booking, calqué sur `GetPartyAccountController` :
lecture seule par `public_id`, jamais l'id interne exposé. PK composite
booking → lookup via QueryBuilder (pas `$em->find()`), même discipline
que `findById` / probe composite PK.

## Livré

- `BookingRepositoryInterface::findByPublicId(PublicId): ?Booking`
- `BookingNotFoundException::forPublicId`
- `BookingResponse` (Money imbriqué `{amount, currencyCode}`, groupes
  `status` / `montants` / `workflow`)
- `GET /api/v1/bookings/{publicId}` — `GetBookingController`
- ExceptionListener : `booking.not_found` → 404
- Tests WebTestCase : 200 sans `id`, 404, 401 sans JWT

## Suite

create HTTP ; sous-ressources (extensions / voyageurs / annulation) ;
pan financier ; autres endpoints.
