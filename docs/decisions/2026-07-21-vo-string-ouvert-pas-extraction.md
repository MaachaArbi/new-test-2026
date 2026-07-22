# Décision — VO string sur référentiel ouvert : pas d’extraction (pour l’instant)

Date : 2026-07-21  
Contexte : clôture `PartyAccountFunctionAssignment`

## Contexte

`PartyRoleCode` et `PartyFunctionCode` partagent une structure quasi identique :
trim, rejet du vide, rejet si trop long (VARCHAR(30)), même forme d’exception
Domain. `phpcpd` ne signale rien — les deux VO sont reformulés différemment au
niveau syntaxique — mais c’est une **duplication conceptuelle** réelle.

## Décision

**Ne pas** extraire de base commune maintenant (ni trait, ni classe abstraite
paramétrée, ni VO générique avec contrainte de longueur).

Deux exemples ne suffisent pas à choisir la bonne forme d’abstraction : décider
trop tôt sur un échantillon de 2 risque de figer une mauvaise forme.

## Différence avec `PublicId`

`PublicId` a été extrait après un seul cas, car il portait une vraie complexité
algorithmique (génération UUID) : une divergence entre deux implémentations
aurait été un risque de bug. Ici : ~8 lignes de validation triviale ; le risque
de divergence est faible, le coût d’indirection d’une abstraction prématurée
n’est pas justifié.

## Déclencheur pour rouvrir

Un **3ᵉ cas réel** de VO string sur référentiel ouvert dans le projet.
Candidat probable : `group_type_code`, ou un futur référentiel similaire ailleurs.

## Mise à jour 2026-07-21

Le 3ᵉ cas est arrivé (`PartyAccountGroupTypeCode`). Décision d’extraction appliquée :
classe abstraite `App\Shared\Domain\ValueObject\OpenReferentialCode`, dont héritent
`PartyRoleCode`, `PartyFunctionCode` et `PartyAccountGroupTypeCode`.

Comportement inchangé (mêmes exceptions / errorCode / traductions) — pure factorisation.
Clôture : `docs/journal/2026-07-21-open-referential-code-extraction.md`.
