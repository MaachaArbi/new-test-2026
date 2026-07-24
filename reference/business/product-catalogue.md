# Métier — Product / Catalogue

**Pas encore balayé.** Compilé depuis `modele-conceptuel-product-catalogue.md`.

---

## Ce que le module décrit

Ce que l'agence **peut vendre** : les fiches produit, indépendamment de tout tarif et de toute
réservation. Neuf familles — hébergement, véhicule, spa, visa, guide, transfert, aérien, bus,
package.

## Comment le métier fonctionne réellement

### Le catalogue décrit, il ne vend pas

Une fiche dit ce qu'est le produit : ses caractéristiques, ses équipements, ses variantes.
Elle ne dit ni son prix, ni sa disponibilité, ni s'il est actuellement commercialisé.

Trois séparations strictes :

- **aucun prix** — c'est Pricing et Contracting ;
- **aucun état actif/inactif** — c'est le Contracting qui décide de ce qui est vendable ;
- **aucun contenu marketing** — descriptions, photos et argumentaires migrent vers un CMS
  séparé.

### Chaque famille a ses spécificités

Un véhicule a une carrosserie, une boîte de vitesses, un carburant, des équipements et des
suppléments. Un spa a des centres, des catégories de soins, des soins. Un visa a ses types
d'entrée et ses pièces exigées. Un bus a ses modèles et ses plans de sièges.

Ces vocabulaires ne se mélangent pas : chaque famille a ses propres référentiels.

### Les plans de sièges

Pour le bus et l'aérien, la disposition physique des sièges est modélisée. La génération d'un
plan reste une logique applicative, pas une mécanique de base de données.

---

## À compléter

- **Qui alimente le catalogue** : saisie manuelle, import fournisseur, données OctaSoft ?
- **Le lien fiche produit ↔ fournisseur** : un même hôtel vendu par plusieurs sources, comment
  s'articule-t-il ?
- **Le maritime n'a aucune fiche** (§51) — trou identifié en session Pricing, jamais comblé.
- **Le contenu riche hébergement** (§32) : descriptions, photos, équipements, capacité — listés
  puis volontairement reportés.
- **Les packages** : comment se composent-ils réellement, avec quelle contrainte de cohérence ?
