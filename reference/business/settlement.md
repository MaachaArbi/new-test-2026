# Métier — Règlements (grand livre client / fournisseur)

**Pas encore balayé.** Compilé depuis `modele-conceptuel-settlement.md` et les décisions du 24/07.

---

## Ce que le module décrit

Qui doit quoi à qui, et ce qui a été payé. C'est le **cœur financier** du système : toute
question de solde, de relance ou de recouvrement se répond ici.

## Le principe fondateur

**Le départ est toujours une réservation.** Elle a un prix d'achat et un prix de vente, ce qui
crée deux dettes symétriques :

```
Vente  → le client doit à l'agence
Achat  → l'agence doit au fournisseur
```

Une réservation est soldée quand :

> total vente = total des règlements client = total facturé
> total achat = total des règlements fournisseur = total facturé fournisseur

Ce contrôle vaut **réservation par réservation**, y compris pour une annulation avec frais :
les frais deviennent le nouveau montant de référence.

## Comment le métier fonctionne réellement

### On n'efface jamais une écriture

Le grand livre est **append-only**, sur le modèle bancaire. Une écriture est un fait daté et
définitif. Une erreur se corrige par une **contre-passation** : on annule par −1 000, puis on
repasse +1 200. Jamais un ajustement de +200.

Deux raisons : un relevé client reste lisible, et un litige reste traçable.

### Le passé ne bouge pas

Chaque écriture porte **deux dates** : celle de sa saisie, et celle de son effet économique.
Une annulation décidée aujourd'hui est datée d'aujourd'hui, même si elle concerne une
réservation de mars. Conséquence : **le solde d'un mois clôturé ne change plus jamais**.

Dans le legacy, une annulation recalculait rétroactivement les soldes passés — un même mois
affichait des chiffres différents selon le jour de consultation.

### Les livres sont séparés par devise

Un client a autant de livres que de devises. Une pièce en dinars ne rencontre jamais un livre
en euros, et **aucune conversion automatique** n'a lieu. C'est ce qui permet à un client
français d'être suivi en euros tout en réservant ponctuellement en dollars.

### Client et fournisseur ne se compensent pas

Un partenaire à qui on vend et achète a deux livres distincts. Éteindre une dette client avec
une créance fournisseur exige un **transfert explicite** — jamais automatique.

Cas d'usage réel : reporter la dette d'un employé parti vers son amicale. Le legacy le faisait
par un bricolage (une ristourne plus une fausse réservation « divers »).

### Le lettrage

Un paiement seul dit qu'un client a versé de l'argent. Le **lettrage** dit *quelle dette* il a
éteinte. Sans lui, on connaît le solde global mais pas ce qui reste dû sur telle réservation.

Il conditionne toute analyse fine — c'est pour cette raison que le plafond n'a pas été
ventilé par service : il aurait dépendu de la qualité du lettrage, rendant son comportement
imprévisible pour un agent.

### L'autorisation de débit a disparu

Dans le legacy, 91 % des pièces étaient des « autorisations de débit » servant uniquement à
exprimer « non payé ». Le solde l'exprime nativement : une dette sans paiement en face est
impayée. L'instrument n'existe plus.

---

## Problèmes du legacy résolus

| Problème | Cause | Résolution |
|---|---|---|
| Milliers d'AD inutiles | L'AD exprimait « non payé » | Un débit sans crédit = impayé, nativement |
| Solde fournisseur incohérent | Montant restant modifiable qui se désynchronise | Append-only : rien à désynchroniser |
| Deux calculs de solde divergents | « Mouvement » défini deux fois | Une seule source de vérité |
| Solde global très lent | Scan de tout l'historique | Solde maintenu en continu |
| Pas de livre par devise | Notion plaquée après coup | Natif dans la clé du livre |
| Solde du passé qui change | Recalcul rétroactif | Date d'effet distincte de la date de saisie |

---

## À compléter

- **Le recouvrement** : comment se déroule une relance ? Y a-t-il des niveaux, des délais, des
  courriers types ? Le rôle « chargé de recouvrement » existe côté Party, mais son processus
  n'est décrit nulle part.
- **Les impayés** : que se passe-t-il concrètement quand un chèque revient impayé, au-delà de
  l'écriture comptable ? Le client est-il bloqué, relancé, son plafond suspendu ?
- **Le remboursement client** : mécanique connue mais jamais éprouvée sur un cas réel (§25).
  Comment ça se passe en pratique — virement, chèque, avoir sur prochaine réservation ?
- **Le transfert inter-livres** : construit, mais jamais validé sur un vrai report de dette
  (§22). Qui l'autorise, avec quelle trace ?
