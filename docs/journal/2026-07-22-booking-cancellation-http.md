## Reprise à froid

Journal — HTTP cancellation policy + tiers.
Date : 2026-07-22
Contrairement aux travelers / hotel-rooms / segments / car-rental (jamais
re-référencés côté client), **créer un palier exige l'identifiant de la

## Origine

```
# TASK — HTTP : politique d'annulation (policy + tier)

## Lecture obligatoire
1. BookingHttpSupport.php (réutiliser tel quel)
2. AddBookingTransportSegmentController.php (référence directe la plus
   proche : Command avec plusieurs champs optionnels, gestion d'erreur
   Domain spécifique)
3. CreateBookingCancellationPolicyHandler / AddBookingCancellationTierHandler
   existants (Application, déjà faits)

## Portée — 2 endpoints
1. POST /api/v1/bookings/{publicId}/cancellation-policy
   Body : { roomId?: int } (bookingId résolu depuis l'URL, pas dans le body)
2. POST /api/v1/bookings/{publicId}/cancellation-policy/tiers
   Body : { policyId: int, daysBeforeStart: int, penaltyType: string,
   penaltyValue?: string, thresholdTime?: string, minStayNights?: int,
   maxStayNights?: int, sortOrder?: int }
   Note : policyId dans le body ici (pas dans l'URL) — cohérent avec le
   Domain existant qui identifie le tier par policyId, pas par booking
   directement. Si tu juges qu'il serait plus propre d'avoir l'URL
   /cancellation-policy/{policyId}/tiers plutôt que policyId en body,
   propose-le et justifie, ne tranche pas silencieusement dans un sens ou
   l'autre.

## DTO requête/réponse
Même discipline que les 3 endpoints précédents : validation Symfony sur
l'input pur, pas de règle Domain dupliquée. penaltyType côté DTO reste une
string (le VO enum PenaltyType::from() peut lever une erreur si invalide —
vérifier ce qui se passe si une valeur hors 'free'/'percentage'/
'fixed_amount' est envoyée : ValueError PHP natif non attrapé remonterait
en 500. Si c'est le cas, ajouter une vérification explicite AVANT
d'appeler PenaltyType::from(), avec une erreur 422 propre — même famille
de trou que service_type_mismatch, ne pas laisser une erreur native PHP
fuiter jusqu'à ExceptionListener).

Réponse : champs métier des deux entités, toujours sans id interne (mais
la policy EST référencée par son id pour créer un tier — donc il FAUT
exposer l'id de la policy dans la réponse de création de policy,
contrairement aux sous-ressources précédentes qui n'étaient jamais
re-référencées. Documenter explicitement pourquoi cette exception à la
règle "zéro id" est nécessaire ici — l'id de policy sert de clé
fonctionnelle pour l'appel suivant, ce n'est pas un identifiant exposé
par confort).

## Erreurs à vérifier
- BookingCancellationRoomMismatchException, BookingCancellationPolicyAlreadyExistsException,
  BookingCancellationPolicyNotFoundException, InvalidBookingCancellationTierException :
  vérifier leur mapping HTTP actuel dans ExceptionListener, corriger/ajouter
  si absent (probablement 409 pour AlreadyExists, 404 pour NotFound, 422
  pour le reste — mêmes conventions déjà établies, ne pas réinventer)

## Tests (WebTestCase, PostgreSQL réel)
Pour chaque endpoint : cas valide, booking/policy introuvable, erreurs
métier propres (pas de 500), input malformé → 422, sans JWT → 401.
Ajouter spécifiquement : penaltyType invalide → 422 propre (pas de 500
ValueError).

## Documentation
- docs/journal/2026-07-2X-booking-cancellation-http.md — documenter
  explicitement l'exception "id exposé" sur la policy et le choix
  d'URL/body pour policyId sur le tier
- docs/STATUS.md : "Booking : CRUD HTTP complet sur tous les sous-domaines
  (pivot, extensions, voyageurs, annulation). Reste : pan financier."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral des
fichiers créés et au moins un fichier de test complet (celui qui couvre
le plus de cas d'erreur distincts).
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
