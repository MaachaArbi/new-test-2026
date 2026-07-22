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
