## Reprise à froid

Journal — 2026-07-22 — BookingCancellationPolicy + Tier.
Barème d'annulation : `booking_cancellation_policy` (agrégat) + `booking_cancellation_tier` (collection 1-N). Confirmé schéma : rattaché **par chambre en général pour l'hôtel** (`room_id` renseigné) ; `room_id` NULL…
Barème d'annulation : `booking_cancellation_policy` (agrégat) +
`booking_cancellation_tier` (collection 1-N). Confirmé schéma : rattaché

## Origine

```
# TASK — Module Booking : BookingCancellationPolicy + BookingCancellationTier

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, tables booking_cancellation_policy
   et booking_cancellation_tier (colonnes exactes, les deux index uniques
   partiels, le commentaire "par chambre en général pour l'hôtel")
2. BookingHotelRoom.php existant (room_id y référence, FK SQL réelle
   possible ici car aucune des deux tables n'est partitionnée)

## Contrainte d'intégrité à vérifier explicitement (pas couverte par la FK SQL)
Si room_id est fourni, il doit appartenir au MÊME booking_id que la
politique créée — la FK SQL vérifie seulement que la room existe quelque
part, pas qu'elle appartient au bon booking. Vérification Application
obligatoire avant insert (charger la room via
BookingHotelRoomRepositoryInterface::findByBookingId, vérifier qu'elle en
fait partie).

## Contrainte d'unicité (miroir des 2 index partiels)
Une seule politique "toute la réservation" (room_id NULL) par booking_id,
et une seule politique par room_id si renseigné. Vérification explicite
Application avant insert (même discipline que office_code, group name...).

## Domain

### BookingCancellationPolicy (agrégat, pas d'update dans cette vague)
src/Modules/Booking/Domain/Entity/BookingCancellationPolicy.php
- id (IDENTITY), bookingId (int), roomId (?int)
- create(bookingId, ?roomId): self

### PenaltyType (enum PHP natif — CHECK SQL fixe, PAS OpenReferentialCode)
src/Modules/Booking/Domain/ValueObject/PenaltyType.php
enum : Free = 'free', Percentage = 'percentage', FixedAmount = 'fixed_amount'

### BookingCancellationTier (collection 1-N, pas d'update, comme BookingHotelRoom)
src/Modules/Booking/Domain/Entity/BookingCancellationTier.php
- id (IDENTITY), policyId (int), daysBeforeStart (int), thresholdTime
  (?string, format HH:MM:SS simple — pas de VO dédié pour un TIME seul),
  minStayNights (?int), maxStayNights (?int), penaltyType (PenaltyType),
  penaltyValue (?string — NUMERIC(14,3), même logique que ExchangeRate :
  jamais de float, string ou VO dédié réutilisant le pattern déjà en
  place), sortOrder (int, défaut 0)
- create(...) : validation — si penaltyType=Free, penaltyValue devrait être
  null (cohérent avec le commentaire schéma) ; si Percentage, valeur entre
  0 et 100 ; si FixedAmount, valeur positive. Lever une exception dédiée
  si incohérent (nouvelle InvalidBookingCancellationTierException)

## Repositories
BookingCancellationPolicyRepositoryInterface : findById, findByBookingId
(retourne la politique "toute réservation" si elle existe),
findByRoomId, existsForBooking(bookingId): bool, existsForRoom(roomId): bool, save
BookingCancellationTierRepositoryInterface : findByPolicyId(array, trié par
sort_order), save

## Application
CreateBookingCancellationPolicy/{Command,Handler} :
- Si roomId fourni : vérifie qu'il appartient bien à bookingId (via
  BookingHotelRoomRepositoryInterface), sinon exception dédiée
- Vérifie existsForBooking/existsForRoom selon le cas avant création

AddBookingCancellationTier/{Command,Handler} :
- Vérifie que policyId existe (BookingCancellationPolicyNotFoundException
  si absent), crée le tier

## Infrastructure
Mapping XML (les deux tables, room_id et policy_id comme bigint simples,
pas d'association Doctrine complexe), Repository Doctrine, migration slice.

## Tests (PostgreSQL réel)
- Politique "toute réservation" (roomId null) → round-trip, unique par
  booking (doublon rejeté avant SQL)
- Politique par chambre → round-trip, room appartenant à un AUTRE booking
  → rejetée explicitement (le vrai test de la faille d'intégrité identifiée)
- Une politique "toute réservation" ET une politique par chambre peuvent
  coexister sur le même booking (ce ne sont pas des doublons entre elles)
- Ajout de plusieurs tiers à une politique, triés par sort_order à la lecture
- penaltyType=Free avec penaltyValue non-null → rejeté par le Domain
- penaltyType=Percentage avec valeur >100 → rejeté par le Domain

## Documentation
- docs/journal/2026-07-2X-booking-cancellation-policy.md
- docs/STATUS.md : "Booking : politique d'annulation faite (policy + tiers,
  intégrité room↔booking vérifiée). Reste : pan financier (différé), HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — BookingCancellationPolicy + Tier

## Contexte

Barème d'annulation : `booking_cancellation_policy` (agrégat) +
`booking_cancellation_tier` (collection 1-N). Confirmé schéma : rattaché
**par chambre en général pour l'hôtel** (`room_id` renseigné) ; `room_id`
NULL = toute la réservation (vol, transfert…).

Intégrité room↔booking **non couverte par la FK SQL** (la FK ne vérifie
que l'existence de la room) → contrôle Application obligatoire via
`BookingHotelRoomRepositoryInterface::findByBookingId`.

Unicité miroir des 2 index partiels : une politique `room_id NULL` par
booking ; une politique par `room_id` si renseigné.

## Livré

- Domain : `BookingCancellationPolicy`, `BookingCancellationTier`,
  `PenaltyType` (enum PHP, CHECK SQL fixe), exceptions dédiées
- Application : `CreateBookingCancellationPolicyHandler` (room ownership +
  unicité) ; `AddBookingCancellationTierHandler`
- Infra : XML + repos ; migration `Version20260722120000`
- Traductions en/fr/ar (4 codes)
- Tests Unit + Integration (unicité, room mismatch cross-booking,
  coexistence whole+room, tri sort_order, Free/Percentage Domain)

## Suite

Pan financier (différé), HTTP.
