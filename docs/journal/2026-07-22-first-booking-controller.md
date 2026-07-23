## Reprise à froid

Journal — 2026-07-22 — Premier Controller Booking (GET public_id).
Premier endpoint HTTP Booking, calqué sur `GetPartyAccountController` : lecture seule par `public_id`, jamais l'id interne exposé. PK composite booking → lookup via QueryBuilder (pas `$em->find()`), même discipline…
Premier endpoint HTTP Booking, calqué sur `GetPartyAccountController` :
lecture seule par `public_id`, jamais l'id interne exposé. PK composite

## Origine

```
# TASK — Premier Controller Booking : lecture par public_id

## Lecture obligatoire
1. GetPartyAccountController.php + ExceptionListener.php (pattern déjà
   validé — même format de réponse, même gestion d'erreurs)
2. Booking.php (tous les champs déjà posés : identité, montants, workflow,
   statut)
3. BookingCompositePrimaryKeyProbeTest.php existant — rappel : public_id
   n'a pas d'unicité stricte en index SQL seul (index composite avec
   booking_date, contrainte du partitionnement), mais l'entropie UUID
   suffit en pratique (même raisonnement déjà validé pour l'ID interne
   via QueryBuilder)

## Portée
UN SEUL endpoint : GET /api/v1/bookings/{publicId}. Pas d'écriture dans ce
prompt.

## 1. Repository — ajouter la méthode manquante
Ajouter à BookingRepositoryInterface : findByPublicId(PublicId $publicId): ?Booking
Implémentation Doctrine : QueryBuilder filtrant sur public_id seul (PAS
$em->find(), même limitation composite PK déjà rencontrée), LIMIT
implicite via getOneOrNullResult().

## 2. DTO de réponse
src/Modules/Booking/Infrastructure/Http/Dto/BookingResponse.php
Champs à exposer (PAS l'id interne, jamais) : publicId, bookingDate
(format Y-m-d), folderId (garder tel quel pour l'instant — pas de
publicId sur BookingFolder exposé séparément dans cette vague), status
(serviceTypeCode, statusCode, channelCode en strings), customerAccountId,
supplierAccountId (nullable), officeAccountId, startDate, endDate
(nullable), montants (achatCurrencyCode, venteCurrencyCode,
totalAchatAmount, totalVenteAmount, margeAgenceAmount,
margeDistributeurAmount, paidAmount, paymentStatus — les Money exposés
comme {amount: int, currencyCode: string} imbriqués, pas juste un entier
brut, pour que le front sache toujours dans quelle devise lire le montant),
workflow (isOnRequest, assignedAgentAccountId, isLocked, isDisputed,
supplierStatusLabel).

## 3. Controller
src/Modules/Booking/Infrastructure/Http/Controller/GetBookingController.php
- Route /api/v1/bookings/{publicId}, requirements UUID (même regex que
  Party)
- 404 via BookingNotFoundException si introuvable (déjà existante,
  vérifier si elle a une variante forPublicId ou juste forId — l'adapter/
  l'étendre si besoin, cohérent avec ce qu'on a fait sur Party)
- Controller minimal, délègue tout

## Tests (WebTestCase, PostgreSQL réel)
- GET sur un booking existant → 200, JSON correct, ZÉRO champ "id"
  interne, structure Money imbriquée vérifiée
- GET sur un public_id inexistant → 404
- Sans JWT → 401 (même protection que Party)

## Documentation
- docs/journal/2026-07-2X-first-booking-controller.md
- docs/STATUS.md : "Premier Controller Booking opérationnel (lecture par
  public_id). Reste : create HTTP, sous-ressources (extensions/voyageurs/
  annulation), pan financier, autres endpoints."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés/modifiés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
