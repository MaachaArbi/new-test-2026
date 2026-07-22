# Journal — 2026-07-22 — Endpoint POST /api/v1/bookings

## Contexte

Création HTTP Booking, calquée sur `CreatePartyAccountController` :
validation Symfony d'input → 422 dans le Controller ; règles Domain →
ExceptionListener. Réutilise `CreateBookingHandler` / `CreateBookingCommand`
et `BookingResponse`.

## Diagnostic — cause du 500 (avant correction)

Reproduit via `CreateBookingHandler` + `serviceTypeCode=not_a_real_service`
(après VO OpenReferentialCode OK) :

1. `Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException`
2. ← `Doctrine\DBAL\Driver\PDO\Exception`
3. ← `PDOException`
4. SQLSTATE **23503** — `booking_service_type_code_fkey`  
   `Key (service_type_code)=(not_a_real_service) is not present in table "booking_service_type".`

Même trou pour `status_code`, `channel_code`, `achat_currency_code` /
`vente_currency_code` (FK vers `booking_status`, `booking_channel`,
`ref_currency`). `PaymentStatus` = enum PHP — hors scope.

## Correction

`BookingReferentialValidator` (Application, DBAL / ADR-003) appelé par
`CreateBookingHandler` **avant** `Booking::create()` :
service / status / channel / currencies.

**Exceptions Domain** — 4 classes séparées (convention projet
1-classe-1-`errorCode()` fixe) :
`BookingUnknownServiceTypeException`, `BookingUnknownStatusException`,
`BookingUnknownChannelException`, `BookingUnknownCurrencyException`.
Mappées **422** via `UNPROCESSABLE_ERROR_CODES` dans ExceptionListener
(erreur de forme d'input côté client).

Note : une première itération regroupait les 4 codes dans une seule
classe paramétrée (`BookingUnknownReferentialCodeException`) — choix
pragmatique qui cassait la convention dont dépendent le catalogue de
traduction et ExceptionListener. Alignement fait pour rester cohérent
avec le reste du projet.

## Frontière HTTP (après correction)

| Cas | HTTP | Mécanisme |
|---|---|---|
| Input malformé (`folderId` manquant / non entier) | **422** | Validator Symfony |
| Code référentiel inconnu (service/status/channel/currency) | **422** | exceptions `BookingUnknown*Exception` |
| `endDate` < `startDate` | **400** | `booking.invalid_dates` (règle métier) |
| Sans JWT | **401** | Security Lexik |

## Livré

- `CreateBookingRequest` + `CreateBookingController` (201 + Location)
- `JsonRequestSupport` (Shared)
- `BookingReferentialValidator` + 4 exceptions dédiées
- Tests WebTestCase (201+GET, 422 référentiels ×5, 400 dates, 422 input, 401)
