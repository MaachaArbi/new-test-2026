# Journal — 2026-07-21 — PartyAccountFunctionAssignment

## Contexte

Assignation de fonction (table `party_account_function`) — Domain + Application +
Infrastructure, même pattern que `PartyAccountRoleAssignment`, avec nuance
structurelle : unicité active = **triplet**
`(person_account_id, function_code, organization_account_id)`.
`organization_account_id` toujours obligatoire (décision #13). Fonction générique
`member` = ex-accès (décision #14).

## Faits

1. Domain : `PartyFunctionCode` (VO string ouvert), `PartyAccountFunctionAssignment`,
   exceptions, interface repo (`assign` / `revoke` / `findById` / `hasActiveFunction`)
2. Application : `AssignPartyAccountFunction` — Handler invocable, vérifie le
   triplet via `hasActiveFunction()` avant écriture
3. Infrastructure : XML mapping, type DBAL `party_function_code`,
   `DoctrinePartyAccountFunctionAssignmentRepository`
4. Tests : Unit VO+Entity ; Integration — round-trip, revoke, **même personne +
   même fonction / deux orgs → autorisé**, doublon triplet rejeté (count=1),
   réassignation après revoke
5. **2ᵉ cas réel VO string ouvert** (`PartyRoleCode` / `PartyFunctionCode`) :
   structures volontairement distinctes pour phpcpd — **pas d’extraction** d’une
   base commune (règle « généraliser au 2ᵉ cas » : signalé ici, pas tranché)
6. Ajustements phpcpd mineurs hors Function : `validFrom()` / `createdBy()` Role
   (intermédiaires locaux), repo Role (`$entityManager`, ordre méthodes) —
   comportement inchangé
7. Correction post-revue : `validFrom()` / `validTo()` Function ramenés à des
   `return` simples (alignés sur l’intention Role — le branchement `isActive()`
   dans `validTo()` était du code mort)

## Résultats des 4 outils

**phpunit** — OK (41 tests, 200 assertions)  
**phpstan** — No errors  
**deptrac** — Violations 0 · Allowed 79 · Uncovered 52  
**phpcpd** — No clones found
