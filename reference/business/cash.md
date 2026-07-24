# Métier — Cash Management (caisses et banques)

**Pas encore balayé.** Compilé depuis `modele-conceptuel-cash-management.md`, les corrections
du 23/07 (§63) et la conception du cycle de vie des bordereaux du 24/07 (§67).

---

## Ce que le module décrit

Le trajet **physique** de l'argent. Règlements dit qui doit quoi ; Cash Management dit où se
trouve concrètement chaque billet et chaque chèque.

## Comment le métier fonctionne réellement

### La caisse est une session, pas un coffre

Il n'existe pas de « caisse » permanente. Un caissier **ouvre une session**, encaisse pendant
sa journée, puis **clôture**. Un caissier central valide ensuite ce qu'il a remis.

Le fond de caisse **ne se transmet jamais** d'une session à la suivante. À chaque validation,
l'argent retourne au caissier central — c'est le principe de l'enveloppe : on repart de zéro.

### Toutes les pièces ne passent pas par la caisse

Chaque mode de règlement a son trajet :

- **espèces, chèque** → passent physiquement par une session de caisse ;
- **virement** → atterrit directement en banque, sans jamais toucher une caisse ;
- **prise en charge, bon de commande** → transmis physiquement à un tiers émetteur (une
  amicale, une entreprise) qui remboursera ;
- **écritures purement scripturales** → Cash Management ne les voit jamais.

Ce routage est **entièrement paramétrable**. Créer un nouveau mode de règlement se fait par
une ligne en base ; aucun code ne connaît de mode en dur.

### Suivre une pièce, ou fondre les montants

Deux comportements possibles selon le mode de règlement :

- **suivi individuel** — le chèque garde son lien vers le client jusqu'à son dépôt en banque.
  Résout la perte de traçabilité des espèces du legacy.
- **agrégé** — tout est fondu en un montant par devise, comportement historique.

Nuance : une espèce suivie individuellement à l'entrée **redevient fongible à la sortie** — un
billet ne porte pas le nom d'un client. Le système trace au mieux en consommant les plus
anciens encaissements d'abord, sans jamais bloquer un décaissement légitime.

### Les bordereaux

Deux mouvements sortants de caisse, structurés pareillement :

- **remise en banque** — le caissier constitue un bordereau de chèques ou d'espèces et se
  déplace à la banque ;
- **transmission externe** — remise physique à un tiers émetteur, qui remboursera plus tard.
  Chaque ligne a son propre statut : une amicale peut rembourser certaines pièces et pas
  d'autres.

**Un bordereau se prépare avant d'être validé.** Le caissier accumule ses chèques pendant la
journée, puis valide au moment de partir. Tant qu'il est en préparation, **rien ne bouge
comptablement** : la caisse affiche toujours ce qu'il y a physiquement dans le tiroir.

Un caissier **ne peut pas clôturer sa session** s'il a un bordereau en préparation : il doit
le valider ou le supprimer. Un brouillon ne survit donc jamais à une clôture.

Un brouillon se **supprime** (rien n'existe encore). Un bordereau validé ne peut qu'être
**annulé avec trace**, par contre-passation — et plus du tout une fois confirmé par la banque.

### Le relevé bancaire

La version de la banque n'est **jamais fusionnée** avec le journal interne. Ce sont deux
sources indépendantes qu'on rapproche ligne à ligne, avec des montants partiels possibles.
Un écart ne bloque rien : il se constate.

### Le double encaissement

Un même chèque ne peut pas être encaissé deux fois dans la même session — c'est toujours une
erreur de saisie. Mais il peut légitimement **réapparaître dans une autre session** : c'est
son parcours normal du caissier agent vers le caissier central, puis vers la banque.

---

## À compléter

- **Le caissier central** : que fait-il exactement à la validation ? Recompte-t-il ? Peut-il
  refuser une session, et alors que devient-elle ?
- **L'écart de clôture** : un type de mouvement existe pour ça. Qui l'autorise, à partir de
  quel montant y a-t-il escalade ?
- **Les transmissions externes** : à qui transmet-on réellement, et selon quel rythme ? Le
  remboursement arrive-t-il par virement, par chèque ?
- **La configuration de caisse du legacy** (écran mappant chaque opération vers un type de
  mouvement) : elle est **paramétrable par client** dans le legacy, alors que le nouveau
  schéma code ces choix en dur dans ses fonctions SQL. À trancher au balayage.
- **Le cycle brouillon → validé** vient d'être conçu mais n'a jamais été confronté au terrain.
