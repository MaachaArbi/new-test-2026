# Métier — Booking (réservations)

**Pas encore balayé.** Compilé depuis `modele-conceptuel-booking.md` et les décisions du 22-24/07.

---

## Ce que le module décrit

L'acte de vente. Une réservation fige ce qui a été vendu, à qui, à quel prix d'achat et de
vente — et sert de point de départ à toute la chaîne financière.

## Comment le métier fonctionne réellement

### Une structure, treize services

Hébergement, vol, maritime, location de voiture, transfert, spa, visa, bus, excursion, guide,
accès piscine, train, divers. Tous partagent le même tronc commun — client, dates, montants,
statut — et seuls ceux qui en ont besoin portent une **extension** dédiée.

Aujourd'hui trois extensions existent : le détail hébergement (arrangement, chambres), les
segments de transport (tronçons, sièges) et le détail location de voiture. Quels services y
ont droit est **une donnée**, pas du code : ouvrir les segments de transport au bus est une
ligne à ajouter.

### Le prix d'achat et le prix de vente sont indépendants

Chacun a sa devise et son taux de change, **figés au jour de la réservation**. On peut acheter
en dollars à un agrégateur et vendre en euros à un client français ; aucune conversion
implicite n'a lieu en base.

### La réservation peut être payée par plusieurs personnes

Une amicale paie 300, l'employé 200. Chacun a alors sa propre dette. C'est ce qui permet de
relancer l'employé sans relancer l'amicale.

### « Sur demande »

Une réservation n'est pas toujours confirmée immédiatement. Elle passe « en demande » pour des
raisons de plusieurs natures :

- **le fournisseur** — stock insuffisant, durée minimale non atteinte, arrêt des ventes,
  chambre sur demande ;
- **l'interne** — solde client insuffisant, ou politique commerciale du compte.

Chaque raison déclenche un circuit différent : notifier le fournisseur, ou soumettre à
l'approbation d'un responsable.

### Le prix de revente du distributeur

Une agence B2B revend à son propre client à son propre prix. Cette information est enregistrée
mais reste **purement informative** — elle sort du périmètre financier de l'agence.

---

## À compléter

- **Le cycle de vie complet** : quels statuts existent, qui a le droit de faire quoi ? Qui
  annule, avec quelles conséquences sur les frais ?
- **Les frais d'annulation** : comment se calculent-ils, qui les décide ?
- **Le dossier de voyage** regroupe plusieurs réservations. Quelle est sa logique métier —
  un voyage, une famille, un devis ?
- **Les services jamais éprouvés** sur données réelles (§15) : vol/billetterie n'a été vu que
  sur captures d'écran, transfert par déduction, excursion/spa/visa/bus/train jamais confrontés.
- **Le maritime** n'a aucune fiche produit au catalogue (§51).
- **Les charges** (`booking_charge`) : leur catégorisation attend que le backend implémente
  le module et remonte ses vrais besoins (§61 volet B).
