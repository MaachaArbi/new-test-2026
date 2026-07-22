# Journal — POST /api/v1/bookings/{publicId}/travelers

Date : 2026-07-22

## Objectif

Premier endpoint HTTP de sous-ressource Booking : ajouter un voyageur
snapshot via le Handler Application déjà existant
(`CreateBookingTravelerHandler`).

## Résolution parent

`publicId` URL → `BookingRepositoryInterface::findByPublicId()` →
`bookingId` interne pour la Command. Absent → `BookingNotFoundException`
(404), même pattern que `GetBookingController`.

## Identifiant voyageur dans la réponse — décision

**Retenu : n'exposer aucun `id` (ni `bookingId`).** Corps 201 = snapshot
métier uniquement (`firstName`, `lastName`, optionnels, `isPaxLeader`, …).

Justification :

1. L'entité n'a **pas** de `publicId` ; exposer la PK interne contredirait
   la règle projet « zéro champ `id` interne » déjà testée sur Booking /
   Party (ADR d'identité publique).
2. Sous-ressource **jamais adressée seule** aujourd'hui (pas de GET/PATCH
   `/travelers/{…}`) — un id client n'a pas de cible HTTP utile.
3. Snapshot **immutable** côté Domain (pas de mutation) : le 201 sert
   d'acquittement / écho, pas de poignée pour des updates.

Si un jour un GET/PATCH individuel apparaît : introduire un `publicId` sur
`booking_traveler` (préférable) plutôt que de promouvoir la PK en API —
revoir alors ce DTO.

Alternative écartée : exposer `id` « exceptionnellement » — trop risqué
comme précédent, alors qu'aucun client ne peut encore s'en servir.

## Pas de header `Location`

Limitation **assumée** : aucun endpoint GET voyageur individuel. Un
`Location` sans URI adressable serait trompeur (contrairement à
`POST /bookings` → `GET /bookings/{publicId}`).

## Pax leader déjà défini → 409

`booking_traveler.pax_leader_already_set` ajouté à
`ExceptionListener::CONFLICT_ERROR_CODES` — même sémantique que les
`*already_active` / `*already_used` Party (état conflictuel), pas 400
par défaut.

## Fichiers

- `AddBookingTravelerRequest.php` / `AddBookingTravelerResponse.php`
- `AddBookingTravelerController.php`
- `ExceptionListener.php` (CONFLICT)
- `AddBookingTravelerControllerTest.php`
