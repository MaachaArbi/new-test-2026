# Journal — HTTP pan financier Booking (charges, settlements, payer-splits)

**Date** : 2026-07-22

## Livré

Trois POST uniquement (pas de GET individuel, pas d'update/delete) :

| Endpoint | Handler Application (inchangé) |
|---|---|
| `POST /api/v1/bookings/{publicId}/charges` | `AddBookingChargeHandler` |
| `POST /api/v1/bookings/{publicId}/settlements` | `AssignBookingSettlementHandler` |
| `POST /api/v1/bookings/{publicId}/payer-splits` | `AssignBookingPayerSplitHandler` |

Pattern commun (réf. `AddBookingCancellationTierController` + `BookingHttpSupport`) :
resolve `publicId` → DTO validé Symfony → Command → **201 Created**, réponse métier **sans id interne** ni `bookingId` (ces agrégats ne sont pas re-référencés par un futur appel HTTP, contrairement à la cancellation policy).

## Nuances input

- **Charges** : Money en champs plats (`achatAmountMinor` / `achatCurrencyCode`, idem vente) ; `metadata` = objet JSON libre (`array`).
- **Settlements** : `beneficiaryRole` via `Assert\Choice` sur `BeneficiaryRole::cases()` (évite ValueError → 500) ; `rate` string optionnelle, format laissé au VO `SettlementRate`.
- **Payer-splits** : miroir direct du Command.

## Mapping HTTP (`ExceptionListener`)

| errorCode | HTTP | Motif |
|---|---|---|
| `booking.unknown_charge_type` | 422 | déjà classé |
| `booking_charge.traveler_mismatch` / `segment_mismatch` | 422 | déjà classé |
| `booking_settlement.already_active` | **409** | cohérent `*AlreadyActive` |
| `booking_payer_split.already_active` | **409** | idem |
| `booking_payer_split.exceeds_total` | **422** | contenu requête vs état existant (plafond), **pas** une transition de statut — 409 réservé aux conflits d'état exclusif (doublon actif) |
| `booking_payer_split.currency_mismatch` | 422 | mismatch contenu |
| `booking_settlement.invalid_rate` / `invalid` | 422 | ajoutés |
| `money.invalid_currency_code` | 422 | devise rejetée par VO Money (ex. settlement « devise invalide ») |

## Tests

WebTestCase × 3 endpoints : 201 sans id, 404 booking, ≥2 erreurs métier, 422 malformé, 401 sans JWT.  
Payer-splits : dépassement de total vérifié jusqu'au JSON (`code` + `context` `already_allocated_minor` / `requested_minor` / `allowed_total_minor`).

## Hors scope

Revoke settlement / payer-split HTTP ; payment ; B15–B18 / C3 ADR-003.

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-financial-http-cloture.md`.
