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
