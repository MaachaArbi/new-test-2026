## Reprise à froid

2026-07-22 — BookingCharge + recalcul applicatif des totaux.
- Agrégat `BookingCharge` (collection simple, pas d’update dans cette vague)
- `AddBookingCharge` (Application) : création + **recalcul des totaux** `booking.total_achat_amount` / `total_vente_amount`
- Migration `booking_charge_type` + `booking_charge`

## Origine

```
# TASK — Module Booking : BookingCharge + recalcul des totaux

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, tables booking_charge_type et
   booking_charge (règle SUM explicitement documentée comme applicative,
   jamais SQL)
2. Booking.php existant (totalAchatAmount/totalVenteAmount actuellement
   fixés uniquement à create(), jamais mutés)
3. BookingReferentialValidator.php existant (réutiliser pour vérifier
   charge_type_code, PAS dupliquer la logique)

## Portée
Uniquement booking_charge (ajout de lignes) + le recalcul des totaux sur
Booking qui en découle. PAS settlement, PAS payer_split, PAS payment
(explicitement "conception provisoire, à revoir" côté BDD — hors périmètre
tant que ce n'est pas retravaillé). PAS de HTTP dans ce prompt.
PAS de category_code sur charge_type (Volet B toujours différé — reste un
simple code référentiel ouvert, pas de branchement de comportement dessus).

## 1. Domain — BookingCharge (nouvel agrégat, collection simple)
src/Modules/Booking/Domain/Entity/BookingCharge.php
- id (IDENTITY), bookingId, travelerId (?int), segmentId (?int),
  chargeTypeCode (string simple, référentiel ouvert vérifié en Application
  comme service_type/status/channel — pas besoin d'OpenReferentialCode
  puisque déjà couvert par BookingReferentialValidator), label (?string),
  metadata (array, JSONB — simple tableau associatif PHP, pas de VO
  dédié), achatAmount (Money), venteAmount (Money), sortOrder (int)
- create(...) : PAS de validation de cohérence devise ici contrairement à
  Booking (une charge peut légitimement être à 0 dans une devise, pas de
  règle croisée connue à ce stade)
- Pas d'update dans cette vague (comme les autres collections simples)

## 2. Domain — Booking : nouvelle méthode de mutation
Ajouter à Booking.php :
recalculateTotals(Money $totalAchatAmount, Money $totalVenteAmount): void
- Réutilise assertMoneyCurrency() existant (privé → vérifier s'il faut le
  rendre accessible en interne ou dupliquer l'assertion minimalement,
  choisir la solution la plus propre)
- Remplace totalAchatAmount/totalVenteAmount stockés
- PAS de recalcul des marges ici (margeAgenceAmount/margeDistributeurAmount
  restent externes à cette règle, le schéma ne les inclut pas dans la
  somme documentée)

## 3. Repository
BookingChargeRepositoryInterface : findByBookingId(bookingId): array
(triée par sort_order), save
Ajouter à BookingRepositoryInterface si absent : une méthode pour
persister Booking après recalcul (save() existe déjà probablement,
vérifier qu'il fonctionne aussi pour une mise à jour, pas seulement une
création — Doctrine gère normalement les deux via persist()+flush() sur
une entité déjà managée, à confirmer par un test)

## 4. Application — la vraie logique métier de cette vague
AddBookingCharge/{Command,Handler} :
- Vérifie chargeTypeCode existe (BookingReferentialValidator, nouvelle
  méthode assertChargeTypeExists si absente)
- Si travelerId fourni, vérifie qu'il appartient au bookingId (même garde-
  fou que room_id sur cancellation policy — réutiliser le même principe,
  factoriser si pertinent)
- Si segmentId fourni, même vérification d'appartenance
- Crée la charge, la sauvegarde
- APRÈS sauvegarde : recalcule les totaux via une requête DBAL SUM sur
  toutes les charges du booking (achat_amount, vente_amount), construit
  les Money correspondants (devise = celle du booking, déjà connue),
  appelle booking->recalculateTotals(), sauvegarde le booking

## Tests (PostgreSQL réel)
- Ajout d'une charge simple → round-trip, total du booking mis à jour
  correctement (vérifier AVANT/APRÈS)
- Ajout de plusieurs charges successives → total cumule correctement à
  chaque ajout (pas juste la dernière charge)
- travelerId n'appartenant pas au booking → rejeté avant SQL
- segmentId n'appartenant pas au booking → rejeté avant SQL
- chargeTypeCode inconnu → 422-style (DomainException), pas de FK SQL qui
  fuite (même discipline que create booking)
- metadata JSONB : round-trip d'un contenu non-trivial (objet imbriqué)

## Documentation
- docs/journal/2026-07-2X-booking-charge.md — expliquer explicitement la
  règle de recalcul applicatif (pourquoi jamais un trigger, citer ADR-002
  et le commentaire du schéma), et pourquoi Volet B (catégorisation) n'est
  toujours pas nécessaire à ce stade (aucun comportement ne branche encore
  sur charge_type_code)
- docs/STATUS.md : "Booking : booking_charge fait (Domain + Application +
  Infrastructure), recalcul automatique des totaux. Reste : settlement,
  payer_split (payment différé, conception provisoire), HTTP charges."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés/modifiés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# 2026-07-22 — BookingCharge + recalcul applicatif des totaux

## Périmètre

- Agrégat `BookingCharge` (collection simple, pas d’update dans cette vague)
- `AddBookingCharge` (Application) : création + **recalcul des totaux** `booking.total_achat_amount` / `total_vente_amount`
- Migration `booking_charge_type` + `booking_charge`
- **Hors périmètre** : settlement, payer_split, payment (conception provisoire BDD), HTTP charges, Volet B (`category_code` sur charge_type)

## Règle de recalcul — pourquoi jamais un trigger

Le commentaire du schéma (`booking_charge`) et **ADR-002** imposent la même discipline :  
`SUM(vente_amount)` (et achat) **doit** égaler les totaux du booking, mais c’est une **règle applicative** — l’Application recalcule et réécrit les totaux à chaque mutation de charge. **Jamais** un trigger SQL ni une contrainte générée.

Motifs :

1. La logique métier reste dans le Domain/Application (testable, explicite, pas de magie BDD).
2. ADR-002 : pas de logique métier dans PostgreSQL (triggers / procédures) pour ce type de cohérence dérivée.
3. Le schéma documente explicitement la SUM comme applicative — le SQL ne fait que stocker les lignes et les montants.

Flux `AddBookingChargeHandler` : save charge → `SELECT COALESCE(SUM(...))` DBAL → `Booking::recalculateTotals(Money, Money)` → `BookingRepository::save` (update d’entité déjà managée via persist+flush).

Les marges (`marge_agence_amount` / `marge_distributeur_amount`) **ne** sont **pas** recalculées ici : hors formule SUM documentée sur les charges.

## Volet B (catégorisation) toujours différé

`charge_type_code` reste un **référentiel ouvert** (table `booking_charge_type`, vérifié via `BookingReferentialValidator::assertChargeTypeExists`).  
Aucun comportement applicatif ne branche encore sur ce code (pas de category, pas de règles par famille de charge). Ajouter un Volet B maintenant serait de la spéculation — reporté tant qu’un besoin concret n’apparaît pas.

## Garde-fous

- `travelerId` / `segmentId` optionnels : appartenance au `bookingId` vérifiée **avant** SQL (même principe que room sur cancellation policy).
- Type de charge inconnu → `BookingUnknownChargeTypeException` (422-style), pas de fuite FK Doctrine.
