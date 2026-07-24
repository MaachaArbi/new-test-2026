# Métier — Facturation et avoirs

**Pas encore balayé.** Compilé depuis `modele-conceptuel-facturation.md` et la session TVA du 24/07.

---

## Ce que le module décrit

L'émission des documents légaux : factures, avoirs, factures et avoirs fournisseurs.

## Comment le métier fonctionne réellement

### La numérotation ne tolère aucun trou

Une facture validée reçoit un numéro dans une séquence **strictement continue** — exigence
légale. Un numéro ne peut être ni sauté, ni réutilisé, ni réattribué.

Conséquence directe : **une facture ne se supprime jamais.** Une erreur se corrige par un
avoir, jamais par une suppression, qui créerait un trou illégal.

Une facture naît donc **en brouillon**, modifiable et sans numéro. La validation lui attribue
son numéro et la fige.

### Deux façons de calculer la TVA

Selon le montage commercial, la TVA porte sur des assiettes différentes :

- **sur le total** — le client paie tout à l'agence, la TVA porte sur le montant complet ;
- **sur la commission** — le client paie l'achat directement à l'hôtel, l'agence ne facture
  que sa commission ; la TVA ne porte que sur elle.

Le legacy appelait la seconde « TVA sur Vente − Achat », ce qui est la même chose : la marge.

À l'émission, l'agent voit **deux listes déroulantes** — le taux et le mode de calcul — et
peut modifier les deux. Un défaut est proposé selon le type de service, mais ce n'est qu'une
proposition.

Cas courants : un client exonéré est à 0 % ; un séjour hôtelier à l'étranger sort du champ de
la TVA tunisienne ; l'hébergement local est à 7 %, la location de voiture aussi, le maritime
à 18 %, les prestations diverses à 19 %.

**Le taux et l'assiette sont figés sur la ligne de facture.** Une facture émise l'an dernier
ne change pas si les taux évoluent.

### Le timbre fiscal

Un montant fixe par document, pas un pourcentage. Il est porté par **une seule ligne** par
couple facture/réservation. Certains clients en sont exonérés, sur attestation.

### Une facture peut mêler plusieurs réservations

Une ligne peut être **ancrée** à une part de réservation précise (donc à un payeur précis, ce
qui gère le cas amicale/employé), ou **libre** — une désignation manuelle sans réservation
derrière.

---

## À compléter

- **Le plan comptable** : les taux de TVA portent un compte déductible et un compte collecté,
  et les tiers un compte comptable et un compte tiers. Ils servent **uniquement à un export
  Excel** vers le comptable externe. Le format de cet export n'est pas décrit.
- **Le cycle des avoirs** : dans quels cas émet-on un avoir plutôt qu'une annulation ?
- **La facture fournisseur** : est-elle saisie, importée, rapprochée automatiquement ?
- **Les factures « passager »** visibles dans le legacy : en quoi diffèrent-elles ?
- **La personnalisation des documents** (§41) : voucher, billet, contrat — moteur de gabarits
  rattaché à la Configuration avancée, jamais cadré en détail.
