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
