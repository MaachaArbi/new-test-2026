## Reprise à froid

Journal — 2026-07-22 — Rollback trait HistorizedBookingChildAccessors.
Lors du nettoyage cosmétique des getters de `BookingPayerSplit` (`return $this->x`), `phpcpd` a signalé des clones avec `BookingSettlement`. Un trait `HistorizedBookingChildAccessors` a été extrait **et appliqué…
Lors du nettoyage cosmétique des getters de `BookingPayerSplit`
(`return $this->x`), `phpcpd` a signalé des clones avec

## Origine

```
# TASK — Annuler le trait, revenir à l'état validé

1. Restaurer BookingSettlement.php à sa forme SANS trait (getters inline
   tels que déjà validés : id(), bookingId(), isActive(), validFrom(),
   validTo(), createdBy() directement dans la classe — la version que
   j'ai vue et validée avant cette vague).

2. Supprimer HistorizedBookingChildAccessors.php.

3. Garder BookingPayerSplit.php avec SES SEULS getters simplifiés
   (return $this->x; direct), mais SANS le trait — chaque getter
   directement dans la classe, comme Booking.php/BookingSettlement.php
   restauré.

4. Sur le clone phpcpd qui va réapparaître entre BookingSettlement et
   BookingPayerSplit (getters triviaux identiques sur les champs
   d'historisation communs) : NE RIEN CORRIGER dans cette vague.
   Documenter dans docs/backlog/todo.md : "Clone phpcpd accepté et
   documenté : getters triviaux d'historisation (id/bookingId/isActive/
   validFrom/validTo/createdBy) dupliqués entre BookingSettlement et
   BookingPayerSplit. Extraction commune (trait ou classe abstraite)
   PROPOSÉE mais reportée — décision d'architecture à valider
   explicitement dans une vague dédiée, pas en réaction automatique à
   phpcpd. Si un 3ème agrégat historisé Booking apparaît avec le même
   besoin, reproposer l'extraction à ce moment-là."

Relance phpcpd — confirmer qu'il détecte de nouveau le clone (comportement
attendu et accepté, pas une erreur à corriger). Relance phpstan/deptrac/
phpunit — tout doit rester vert sauf phpcpd qui doit désormais signaler
ce clone spécifique, accepté.

Documentation :
- docs/journal/2026-07-22-revert-historized-accessors-trait.md :
  documenter le rollback, la raison (décision d'architecture prise hors
  périmètre sans validation), et la référence vers todo.md pour la
  proposition différée
- docs/STATUS.md

Colle le contenu de BookingSettlement.php et BookingPayerSplit.php après
restauration, et les résultats des 4 outils (phpcpd doit maintenant
signaler le clone, c'est le résultat attendu).
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
