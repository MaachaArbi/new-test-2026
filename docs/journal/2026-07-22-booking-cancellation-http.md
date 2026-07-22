# Journal — HTTP cancellation policy + tiers

Date : 2026-07-22

## Endpoints

| Méthode | Path | Succès |
|---|---|---|
| POST | `/api/v1/bookings/{publicId}/cancellation-policy` | 201 |
| POST | `/api/v1/bookings/{publicId}/cancellation-policy/{policyId}/tiers` | 201 |

## Exception « id exposé » sur la policy

Contrairement aux travelers / hotel-rooms / segments / car-rental (jamais
re-référencés côté client), **créer un palier exige l'identifiant de la
policy**. L'entité n'a pas de `publicId` Domain.

**Retenu : exposer `id` (int) dans la réponse 201 policy** — clé
fonctionnelle pour l'appel suivant, pas un confort cosmétique. `bookingId`
reste masqué (parent déjà adressé par `publicId` URL). `roomId` métier
exposé.

Le palier (tier) **n'expose pas** son propre `id` (pas encore de
sous-ressource adressée par id de tier) ; `policyId` est repris dans la
réponse tier (écho de la clé fonctionnelle parent).

## Choix URL pour `policyId` (body vs path)

Deux options considérées :

1. **Body** `POST …/cancellation-policy/tiers` + `{ policyId }` — aligné
   strictement sur `AddBookingCancellationTierCommand`.
2. **Path** `POST …/cancellation-policy/{policyId}/tiers` — nesting REST.

**Retenu : option 2 (path).**

Justification :

- l'`id` policy est déjà la poignée publique minimale après create ;
  le placer dans l'URL formalise la collection nested ;
- le Controller vérifie que `policy.bookingId === booking.id` (404 sinon)
  — empêche d'attacher un palier à une policy d'un autre booking en
  connaissant seulement un id numérique ;
- le body du tier reste purement métier (pénalité, délais), sans redondance
  d'identité.

## `penaltyType` invalide → 422 (pas 500)

`Assert\Choice` sur les valeurs de `PenaltyType` **avant**
`PenaltyType::from()` — un ValueError PHP natif ne traverse plus jusqu'à
un 500.

## Mapping ExceptionListener

| errorCode | HTTP |
|---|---|
| `booking_cancellation_policy.not_found` | 404 |
| `booking_cancellation_policy.already_exists` | 409 |
| `booking_cancellation_policy.room_mismatch` | 422 |
| `booking_cancellation_tier.invalid_penalty` | 422 |

## Fichiers

- DTO / Controllers policy + tier
- Tests HTTP × 2
- `ExceptionListener.php`
