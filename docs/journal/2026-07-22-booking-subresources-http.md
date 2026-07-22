# Journal — HTTP sous-ressources Booking (hotel room, transport, car rental)

Date : 2026-07-22

## Endpoints

| Méthode | Path | Handler Application | Statut succès |
|---|---|---|---|
| POST | `/api/v1/bookings/{publicId}/hotel-rooms` | `AddBookingHotelRoom` | 201 |
| POST | `/api/v1/bookings/{publicId}/transport-segments` | `AddBookingTransportSegment` | 201 |
| PUT | `/api/v1/bookings/{publicId}/car-rental-detail` | `SetBookingCarRentalDetail` | **200** |

PUT car-rental : extension 1-1, sémantique *set* (create ou replace) —
pas un ajout à une collection → 200, pas 201.

Pas de `Location` (aucun GET individuel). Pas d'`id` / `bookingId` dans
les réponses (même règle que travelers).

## `booking.service_type_mismatch` → **409 Conflict**

Avant ce mapping : absent de toutes les listes → **400** par défaut.

**Décision : 409**, une fois pour toutes.

- Ce n'est **pas** une erreur de forme d'input : le body hotel-room /
  segment / car-rental peut être parfaitement valide.
- C'est un **conflit avec l'état courant** du booking (`service_type_code`
  n'autorise pas l'extension demandée via `booking_service_type_extension`).
- Distinct des `booking.unknown_*` (422) : ceux-là portent sur des codes
  absents du référentiel **dans le body de création** ; ici le code
  service_type du booking est valide, mais incompatible avec l'opération.
- Aligné avec les autres CONFLICT (`*already_active`, pax leader) :
  état ressource incompatible avec la mutation demandée.

Ajouté dans `ExceptionListener::CONFLICT_ERROR_CODES`.

## Anti-phpcpd

`BookingHttpSupport` : `requireByPublicId` + `json` (corrélation).

## Fichiers

- Controllers + DTO req/resp × 3
- `BookingHttpSupport.php`
- Tests HTTP × 3
- `ExceptionListener.php` (CONFLICT)
