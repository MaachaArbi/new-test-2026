## Reprise à froid

Journal — 2026-07-21 — RevokePartyAccountRole (Application).
Le Domain `PartyAccountRoleAssignment::revoke()` et le repository `revoke()` existaient déjà ; il manquait le use case Application qui les relie. C’est le dernier morceau pour clore complètement l’agrégat rôle…
Le Domain `PartyAccountRoleAssignment::revoke()` et le repository
`revoke()` existaient déjà ; il manquait le use case Application qui les

## Origine

```
# TASK — Module Party : RevokePartyAccountRole (Application)

## Contexte
PartyAccountRoleAssignment.revoke() existe côté Domain depuis la première vague,
et PartyAccountRoleAssignmentRepositoryInterface::revoke() existe côté
Infrastructure — mais aucun cas d'usage Application ne les relie. C'est le seul
morceau manquant pour clore complètement l'agrégat "rôle".

## Fichiers à créer

src/Modules/Party/Application/RevokePartyAccountRole/
├── RevokePartyAccountRoleCommand.php   — porte l'ID de l'assignation à révoquer
│                                          (assignmentId: int) — PAS accountId+
│                                          roleCode : on révoque une ligne précise,
│                                          identifiée, pas "le rôle X du compte Y"
│                                          (il pourrait y avoir eu plusieurs
│                                          assignations dans le temps)
└── RevokePartyAccountRoleHandler.php   — charge via findById(), vérifie que
                                           l'assignation existe (sinon lever une
                                           exception Domain dédiée à créer :
                                           PartyAccountRoleAssignmentNotFoundException,
                                           même famille DomainException), appelle
                                           $assignment->revoke() (Domain), puis
                                           repository->revoke($assignment)
                                           (respecte la précondition déjà
                                           documentée : c'est la même instance
                                           chargée dans ce Handler, jamais
                                           reconstruite)

## Tests
Unit : pas nécessaire de nouveau (la logique revoke() Domain est déjà testée) —
sauf si le Handler a une logique propre à tester en isolation (mock du repository)

Integration (PostgreSQL réel), ajouter au test existant ou nouveau fichier :
- Révoquer une assignation active via le Handler → valid_to bien persisté
- Révoquer un ID inexistant → PartyAccountRoleAssignmentNotFoundException
- Révoquer une assignation déjà révoquée → l'exception Domain existante
  (déjà testée niveau Domain, vérifier qu'elle remonte bien intacte à travers
  le Handler, avec son errorCode/context préservés)

## Documentation
- docs/journal/2026-07-2X-party-revoke-role.md
- docs/STATUS.md : Party — "RevokePartyAccountRole clos. Agrégat rôle 100%
  complet (assign + revoke)."
- docs/backlog/todo.md : retirer l'item Revoke, restent Core / Controller /
  décision VO ouvert

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — RevokePartyAccountRole (Application)

## Contexte

Le Domain `PartyAccountRoleAssignment::revoke()` et le repository
`revoke()` existaient déjà ; il manquait le use case Application qui les
relie. C’est le dernier morceau pour clore complètement l’agrégat rôle
(assign + revoke).

## Faits

1. `RevokePartyAccountRoleCommand` porte `assignmentId` (ligne précise),
   pas `accountId` + `roleCode`
2. Handler : `findById` → NotFound Domain si absent → `$assignment->revoke()`
   → `repository->revoke($assignment)` (même instance gérée)
3. Exception `PartyAccountRoleAssignmentNotFoundException`
   (`party_account_role.assignment_not_found`, contexte `assignment_id`)
4. Integration : revoke via Handler persiste `valid_to` ; ID inexistant ;
   déjà révoquée remonte `InvalidPartyAccountRoleAssignmentException`
   intacte (errorCode + context)

## Résultats des 4 outils

**phpunit** — OK (76 tests, 366 assertions)  
**phpstan** — No errors  
**deptrac** — Violations 0 · Allowed 165 · Uncovered 63  
**phpcpd** — No clones found
