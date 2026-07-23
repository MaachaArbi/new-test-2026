## Reprise à froid

Journal — HTTP sous-ressources Booking (hotel room, transport, car rental).
Date : 2026-07-22
PUT car-rental : extension 1-1, sémantique *set* (create ou replace) —
pas un ajout à une collection → 200, pas 201.

## Origine

```
# TASK — HTTP : sous-ressources restantes (hotel room, transport segment, car rental detail)

## Lecture obligatoire
1. AddBookingTravelerController.php (pattern de référence complet, à
   répliquer 3 fois — résolution publicId→booking, DTO+validation,
   201 sans Location, pas d'identifiant interne exposé)
2. AddBookingHotelRoomCommand/Handler, AddBookingTransportSegmentCommand/
   Handler, SetBookingCarRentalDetailCommand/Handler (déjà existants,
   Application)
3. ExceptionListener.php — vérifier explicitement si
   'booking.service_type_mismatch' figure dans une des listes de mapping
   HTTP. Si absent, il tombe actuellement en 400 par défaut — décider si
   c'est correct ou si ça mérite 422 (cohérent avec "erreur de forme
   d'input" déjà tranché pour les référentiels inconnus) ou 409 (conflit
   avec l'état du booking). Trancher et documenter, pas laisser au hasard
   du défaut.

## Portée — 3 endpoints
1. POST /api/v1/bookings/{publicId}/hotel-rooms
2. POST /api/v1/bookings/{publicId}/transport-segments
3. PUT /api/v1/bookings/{publicId}/car-rental-detail (PUT, pas POST : 1-1,
   c'est un "set", cohérent avec SetBookingCarRentalDetailHandler déjà
   nommé "Set" pas "Create" — sémantique idempotente d'écrasement,
   pas un ajout à une collection)

Pour chacun : DTO requête (validation Symfony sur l'input pur, mêmes
principes que AddBookingTravelerRequest), Controller (résolution
publicId→booking, 404 si absent, délégation au Handler existant, 201 pour
les deux POST / 200 pour le PUT car_rental puisque "set" peut aussi bien
créer que remplacer), DTO réponse (champs métier, PAS d'id interne — même
règle que traveler, ces sous-ressources n'ont pas de publicId Domain).

## Erreurs
Chacun doit propager BookingServiceTypeMismatchException correctement
mappée (cf. décision ci-dessus sur son code HTTP) si le service_type ne
correspond pas — tester explicitement ce cas sur les 3 endpoints, pas
supposé identique juste parce que Application est déjà testée.

## Tests (WebTestCase, PostgreSQL réel), pour CHACUN des 3 endpoints
- Cas valide → 201/200, champs corrects, pas d'id interne
- Booking inexistant → 404
- Mauvais service_type (ex: hotel-room sur un booking 'flight') → code
  HTTP cohérent avec la décision tranchée plus haut
- Input malformé → 422
- Sans JWT → 401

Pour transport-segments spécifiquement : test d'ajout de deux segments
successifs (aller + retour), vérifier que les deux sont bien créés (pas
un endpoint de lecture nécessaire ici, juste vérifier via 2 x 201 sans
erreur de doublon).

## Documentation
- docs/journal/2026-07-2X-booking-subresources-http.md — documenter le
  choix de code HTTP pour service_type_mismatch une fois pour toutes
- docs/STATUS.md : "Booking : toutes les sous-ressources exposées en
  HTTP. Reste : pan financier, cancellation policy/tier HTTP (à part,
  vague suivante si souhaité)."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés (les 3 DTO requête, 3 DTO réponse, 3 Controllers) et
des 3 fichiers de test, plus les résultats. Vu le volume, si nécessaire,
regroupe la remontée en plusieurs messages plutôt que de résumer.
```

## Décisions prises

Décisions attribuées :
- Mandat de décision délégué par le prompt d'origine (Cursor — à valider)

---

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
