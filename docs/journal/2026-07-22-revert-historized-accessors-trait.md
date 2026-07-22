# Journal — 2026-07-22 — Rollback trait HistorizedBookingChildAccessors

## Contexte

Lors du nettoyage cosmétique des getters de `BookingPayerSplit`
(`return $this->x`), `phpcpd` a signalé des clones avec
`BookingSettlement`. Un trait `HistorizedBookingChildAccessors` a été
extrait **et appliqué aussi à Settlement** sans validation — décision
d’architecture hors périmètre.

## Rollback (cette vague)

1. `BookingSettlement.php` restauré : getters inline, **sans** trait
   (forme déjà validée).
2. `HistorizedBookingChildAccessors.php` **supprimé**.
3. `BookingPayerSplit.php` : getters simplifiés **inline**, sans trait.

## Clone phpcpd

Le clone sur les getters d’historisation triviaux
(`id` / `bookingId` / `isActive` / `validFrom` / `validTo` / `createdBy`)
entre Settlement et PayerSplit est **attendu et accepté**.
Pas de correction dans cette vague.

Proposition différée (extraction trait / classe abstraite) :
voir `docs/backlog/todo.md` — à valider explicitement plus tard,
idéalement si un 3ᵉ agrégat historisé Booking apparaît.
