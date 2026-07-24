# Métier — Pricing (marges de vente)

**Pas encore balayé.** Compilé depuis `modele-conceptuel-pricing.md`.

---

## Ce que le module décrit

Comment se détermine le prix de vente à partir du prix d'achat. Autrement dit : **la marge**.

⚠️ Ce module ne contient **pas** le contracting hôtelier — les tarifs d'achat négociés avec
les hôtels sont un sujet distinct, volontairement gardé pour la fin du projet.

## Comment le métier fonctionne réellement

### Trois natures de règle

- **Marge** — ce que l'agence ajoute au prix d'achat pour son propre compte.
- **Commission** — ce qu'elle reverse ou perçoit vis-à-vis d'un partenaire.
- **Modalité de paiement** — les conditions d'acompte et de solde selon le montage.

### Une règle vise une cible

Une règle peut s'appliquer à tout le monde, à un compte précis, ou à un **groupe de comptes**
— ce qui permet d'accorder les mêmes conditions à un ensemble d'agences partenaires sans les
paramétrer une par une.

### Les critères propres à un service ne se mélangent jamais

Les dates de séjour concernent l'hébergement, le pays de départ concerne le vol. Ces critères
vivent dans des tables **dédiées à chaque service**, jamais dans une table générique — même au
prix de répétition. C'est un choix assumé : une table de critères générique deviendrait
illisible et impossible à contrôler.

### La marge se décline par type de passager

Adulte, enfant, bébé peuvent avoir des valeurs différentes — nécessaire pour l'aérien. Les
services qui n'en ont pas besoin (l'hébergement) laissent ces valeurs vides.

### Qui encaisse quoi

Les modalités de paiement disent **qui collecte l'acompte et qui collecte le solde** : l'agence
ou le fournisseur. C'est ce montage qui détermine ensuite comment la TVA est calculée — sur
le total si l'agence encaisse tout, sur la commission si elle ne perçoit que sa part.

---

## À compléter

- **Comment une marge se décide** en pratique : négociation commerciale, grille type,
  saisonnalité ?
- **Les remises** : une règle peut être négative. Qui autorise une remise, y a-t-il un plafond ?
- **Les micro-marges de contrat** (§52) — par arrangement, par tranche d'âge enfant, par
  réduction chambre — relèvent du Contracting et n'ont jamais été cadrées.
- **Le maritime** n'est pas couvert (§51).
