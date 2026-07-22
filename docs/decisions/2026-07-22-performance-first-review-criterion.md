# Décision — Critère de revue « performance first »

**Date :** 2026-07-22  
**Statut :** adopté  
**Liens :** ADR-003 (lectures DBAL) ; priorité projet OsTravel

## Décision

**Priorité #1 non négociable du projet : PERFORMANCE.**  
**Priorité #2 : SÉCURITÉ.**

Toute décision de conception qui les compromet doit être **signalée explicitement**, jamais glissée en silence (revue de code, journal, ou note dans la PR / le prompt de vague).

## Rappel opérationnel concret (ADR-003)

**AUCUNE lecture** ne doit passer par Doctrine ORM (`EntityManager`, `QueryBuilder` → `select(entité)`), uniquement par **DBAL** (`Connection`, SQL direct).

**Exception légitime :** charger une entité via ORM est acceptable **UNIQUEMENT** quand l’objectif est de la **MUTER** puis la **sauvegarder** (ex. `findById()` avant `recalculateTotals()` + `save()`) — jamais pour une simple lecture d’information ou une vérification d’appartenance / existence.

Sont donc interdits en ORM (liste non exhaustive) :

- existence / unicité (`EXISTS`, `COUNT`, `find* !== null` sans mutation) ;
- appartenance (charger une collection puis boucler en PHP pour un id) ;
- hydratation auxiliaire (ex. lire 2 codes devise du booking parent pour enrichir une autre ligne) ;
- résolution d’identité pure (`public_id` → `id` sans mutate du booking) ;
- GET / projection d’affichage (à migrer vers DBAL / DTO ; les commentaires historiques « 1-ligne acceptable » sont **obsolètes** au regard de cette décision).

## Conséquence pour les revues

Avant de clore toute vague touchant un Repository : vérifier qu’aucune lecture pure ne passe par l’ORM.  
Voir aussi `docs/backlog/todo.md` (section Transverse) et l’audit `docs/journal/2026-07-22-adr003-full-audit.md`.
