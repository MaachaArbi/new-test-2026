## Reprise à froid

Journal — POST /api/v1/bookings/{publicId}/travelers.
Premier endpoint HTTP de sous-ressource Booking : ajouter un voyageur snapshot via le Handler Application déjà existant (`CreateBookingTravelerHandler`).
Date : 2026-07-22
Premier endpoint HTTP de sous-ressource Booking : ajouter un voyageur

## Origine

```
# TASK — Endpoint HTTP : ajouter un voyageur à un booking

## Lecture obligatoire
1. CreateBookingController.php (pattern déjà validé — DTO requête,
   validation Symfony, erreurs Domain)
2. CreateBookingTravelerCommand/Handler existants (déjà fait, Application)
3. GetBookingController.php (résolution publicId → entité, pattern à
   réutiliser ici pour retrouver l'ID interne du booking)

## Portée
UN SEUL endpoint : POST /api/v1/bookings/{publicId}/travelers

## Résolution de la ressource parente
Le Controller reçoit publicId (booking) dans l'URL, doit résoudre l'ID
interne AVANT de construire CreateBookingTravelerCommand (qui attend
bookingId: int). Utiliser BookingRepositoryInterface::findByPublicId(),
404 (BookingNotFoundException) si absent — cohérent avec GetBookingController.

## DTO de requête
src/Modules/Booking/Infrastructure/Http/Dto/AddBookingTravelerRequest.php
Validation Symfony sur l'input pur (pas de règle Domain dupliquée) :
- firstName, lastName : #[Assert\NotBlank]
- civility, phone, email, birthPlace, documentType, documentNumber,
  drivingLicenseNumber, ticketNumber, pnr, travelClass : optionnels,
  #[Assert\Type('string')] si fournis
- age : #[Assert\Type('integer')] si fourni
- birthDate : #[Assert\Date] si fourni
- nationalityCountryId, residenceCountryId, hotelRoomId, partyAccountId :
  #[Assert\Type('integer')] si fournis
- isPaxLeader : #[Assert\Type('boolean')], défaut false

## Controller
src/Modules/Booking/Infrastructure/Http/Controller/AddBookingTravelerController.php
- Résout publicId → booking (404 si absent)
- Valide le DTO (422 si échec, même pattern JsonRequestSupport déjà
  extrait)
- Construit CreateBookingTravelerCommand avec l'ID interne résolu
- 201 Created (pas de Location — cette sous-ressource n'a pas d'endpoint
  GET individuel pour l'instant, noter ça comme limitation assumée dans
  le journal, pas un oubli)
- Corps de réponse : DTO minimal (id du voyageur exposé comment ? cette
  entité n'a pas de publicId — décide et documente : soit on expose l'id
  interne ICI exceptionnellement en le justifiant clairement dans le
  journal — cas d'une sous-ressource jamais adressée seule depuis
  l'extérieur, pas un identifiant public au sens ADR-018 — soit on
  n'expose que les champs métier sans identifiant. Choisis la solution la
  plus cohérente avec l'esprit du projet et justifie-la, ne tranche pas
  silencieusement)

## Erreurs à propager correctement
- BookingTravelerPaxLeaderAlreadySetException (déjà existante) → vérifier
  son mapping HTTP actuel dans ExceptionListener (probablement absente de
  toutes les listes actuelles → tomberait en 400 par défaut ; vérifier si
  c'est le bon code ou si ça mérite 409 Conflict comme les
  *AlreadyActive/AlreadyUsed de Party — cohérence à vérifier, pas supposer)

## Tests (WebTestCase, PostgreSQL réel)
- Ajout voyageur simple → 201, champs corrects
- Ajout sur booking inexistant → 404
- Deuxième pax leader sur le même booking → code HTTP vérifié et cohérent
  avec le reste du projet
- Input malformé (firstName manquant) → 422
- Sans JWT → 401

## Documentation
- docs/journal/2026-07-2X-add-booking-traveler-endpoint.md — justifier le
  choix d'exposition de l'identifiant du voyageur, et l'absence de header
  Location
- docs/STATUS.md
- docs/backlog/todo.md : reste les autres sous-ressources (hotel room,
  transport segment, car rental detail, cancellation policy/tier), pan
  financier

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
