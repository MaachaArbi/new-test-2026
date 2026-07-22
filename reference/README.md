# Référence projet OsTravel — à lire par Cursor/Claude Code avant tout module

**Ce dossier est en lecture seule.** Aucun agent ne doit jamais modifier son contenu —
c'est la mémoire figée des décisions prises en amont (conception BDD + cadrage backend).
Toute correction nécessaire doit remonter à l'utilisateur, pas être appliquée ici.

## Ordre de lecture recommandé, avant de concevoir un module backend

1. `backend-cadrage/00-backend-project-overview.md` — vision, stack, périmètre
2. `backend-cadrage/01-backend-architecture-decisions.md` — ADR backend (ce qui est
   confirmé, reformulé, remplacé, en attente)
3. `backend-cadrage/02-backend-module-index.md` — état de chaque module, dépendances
4. `meta/00-INDEX.md` — vue d'ensemble de la conception BDD
5. `meta/01-architecture_decisions.md` — les 18 ADR BDD (notamment ADR-002 logique
   métier hors DB, ADR-017 RBAC, ADR-018 BIGINT+public_id)
6. `meta/sujets-reportes.md` — sujets explicitement hors périmètre, à ne jamais
   improviser côté backend
7. `conceptual-models/modele-conceptuel-<module>.md` — LE document à lire en premier
   pour comprendre le *pourquoi* avant de toucher au module correspondant
8. `schemas/schema-<module>-v1.sql` (+ `.diff` associés si le module a été réouvert) —
   la structure réelle, seule source de vérité sur les colonnes/contraintes

## Règle absolue

Aucune règle métier ne doit être déduite, supposée ou "améliorée" par un agent sans
être explicitement présente dans un de ces documents. En cas de doute ou d'absence
d'information, s'arrêter et demander — ne jamais halluciner une règle plausible.

## Modules figés à ce jour (voir meta/00-INDEX.md pour le détail exact)

Party, Core, Référentiel commun, Booking, Règlements, Cash Management, Point de vente,
Référentiel Hébergement & Géographie, Facturation/Avoirs, Product/Catalogue, Pricing,
Log, Provider Integration, Permissions/Franchise/Config avancée.

## Mise à jour de ce package

Ce package est une photo prise le 2026-07-21. Si de nouveaux modules sont figés côté
conception BDD après cette date, l'utilisateur les ajoutera manuellement — ne pas
supposer que ce dossier est toujours exhaustif sans vérification.
