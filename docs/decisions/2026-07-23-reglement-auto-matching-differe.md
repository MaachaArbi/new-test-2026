# Décision — Lettrage automatique Règlements différé

**Date :** 2026-07-23  
**Statut :** différé (reprise pendant le chantier frontend)

## Contexte

Cadrage du 23/07 avec l’utilisateur a révélé que « orchestration
auto-matching » (`docs/STATUS.md`, `docs/backlog/todo.md`) mélangeait en
réalité **3 mécanismes distincts** :

1. **Rattachement automatique immédiat** pièce↔créance au moment d’une
   réservation web payée par CB — aucun appelant existant aujourd’hui (pas
   de flux de réservation web, pas de Provider Integration côté backend).
   Différer sans coût : réutilisera la même primitive de lettrage que le
   mécanisme 3, à ajouter en plus, jamais à refaire.

2. **Lettrage manuel** — déjà construit (`CreateReglementMatching`).

3. **Traitement déclenché par bouton**, sur un ou plusieurs comptes
   (filtré par affilié / groupe d’affiliés — `party_account_group`, déjà
   existant), avec choix de stratégie entre l’utilisateur : ancienneté de
   la pièce/créance, **ou** proximité de la date de consommation du
   service (donnée qui vit dans Booking, pas dans Règlements — varie par
   service : date d’arrivée hôtel, date de départ billet, date de
   réception voiture…). Traitement pouvant porter sur un grand nombre de
   comptes → nécessite un système de **jobs asynchrones** (file
   d’attente + worker), qui n’existe pas du tout dans le backend actuel
   (aucune infrastructure de queue/worker branchée).

## Décision

Ne **pas** trancher les règles métier maintenant (difficile sans voir les
écrans réels). Reprendre ce sujet **pendant le chantier frontend**, en
comparant au legacy à ce moment-là.

Le lettrage manuel (mécanisme 2) reste disponible. Les mécanismes 1 et 3
restent hors périmètre runtime tant que cette décision n’est pas rouverte.
