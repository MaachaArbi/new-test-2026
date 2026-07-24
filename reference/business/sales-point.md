# Métier — Point de vente

**Pas encore balayé.** Compilé depuis `modele-conceptuel-sales-point.md`.

---

## Ce que le module décrit

Les **sites physiques** rattachés à un bureau : un comptoir dans un centre commercial, une
agence de quartier, un guichet d'aéroport.

## Comment le métier fonctionne réellement

### Un point de vente n'est pas une entité légale

C'est la distinction essentielle. Un **bureau** est une entité juridique avec son matricule
fiscal ; un **point de vente** est un lieu où l'on vend, rattaché à un bureau. Il ne facture
pas en son nom propre, il n'a pas de comptabilité séparée.

### Il ne disparaît jamais

Un site qui ferme est **désactivé**, jamais supprimé. L'historique des réservations doit
rester lisible indéfiniment : savoir qu'une vente de 2024 a eu lieu au comptoir de Sousse doit
rester possible même si ce comptoir n'existe plus.

### Deux points de vente par réservation

Une réservation peut avoir été **saisie** dans un point de vente et **encaissée** dans un
autre. Le client réserve à un comptoir, paie ailleurs. Les deux informations sont conservées
séparément.

---

## À compléter

- **Le rattachement agent ↔ point de vente** : un agent est-il affecté à un site, peut-il
  tourner entre plusieurs ?
- **Le rapport de rendement par point de vente et par agent** (§29) : identifié comme un vrai
  besoin — mesurer la performance, calculer des primes — mais explicitement reporté comme
  module futur, à ouvrir après la mise en production.
