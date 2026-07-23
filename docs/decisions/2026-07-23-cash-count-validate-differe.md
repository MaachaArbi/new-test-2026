# Décision — Comptage / validation session caisse différés

**Date :** 2026-07-23  
**Statut :** différé (reprise pendant le chantier frontend)

## Contexte

Le schéma Cash Management prévoit déjà `cash_count_session_currency` et
`cash_validate_session` (fermeture réelle / validation caissier central).
Le backend a aujourd’hui `cash_session` (open/close), `cash_movement` et
l’encaissement d’instrument — mais **pas** encore le contrat API ni les
écrans qui fixeront comment comptage et validation s’exposent.

Construire ces fonctions « à l’aveugle » côté Application/HTTP avant le
chantier frontend risquerait de figer un contrat provisoire à reprendre.

## Décision

Ne **pas** brancher le comptage ni la validation session maintenant.
Reprendre ces sujets **pendant le chantier frontend**, quand le vrai
contrat API sera visible (et comparable au legacy).

L’infra `cash_session_balance` (snapshot + trigger) reste en place pour
alimenter les lectures futures — ce n’est pas le comptage/validation.

## Conséquence

`docs/backlog/todo.md` marque comptage/validation comme différés, avec
lien vers cette décision.
