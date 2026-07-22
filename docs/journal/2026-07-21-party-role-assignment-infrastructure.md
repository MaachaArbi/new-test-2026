# Journal — 2026-07-21 — Infrastructure PartyAccountRoleAssignment

## Contexte

Infrastructure + Application pour l’assignation de rôle. La contrainte DB
`uq_party_account_role_active` reste un **filet** ; la règle métier est vérifiée
en PHP dans `AssignPartyAccountRoleHandler` (ADR-002).

## Faits

1. Mapping XML `PartyAccountRoleAssignment.orm.xml` + type DBAL `party_role_code`
2. `DoctrinePartyAccountRoleAssignmentRepository` : `assign` / `revoke` / `findById`
   / `hasActiveRole` — lecture `hasActiveRole` ajoutée à l’interface Domain
   (justification : nécessaire à la règle Application ; pas un update/delete générique)
3. Application `AssignPartyAccountRole` : Command + Handler ; lève
   `PartyAccountRoleAlreadyActiveException` si rôle déjà actif
4. Domain `PartyAccountRoleAssignment` **non modifié**
5. Tests Integration PostgreSQL : round-trip, revoke, doublon rejeté avant SQL
   (assert count=1 après exception), réassignation après revoke
6. `revoke()` repo : précondition documentée (entité gérée / findById même requête) ;
   pas encore de use case Application `RevokePartyAccountRole`
7. `AssignPartyAccountRoleHandler` = service invocable (`__invoke`), **pas**
   `#[AsMessageHandler]` — bus Messenger (ADR-003) à brancher plus tard

## Deptrac

Couche `Application` déjà autorisée → `SharedDomain` + `ModuleDomain` — pas
d’assouplissement nécessaire.

## Clôture

Validé — voir `2026-07-21-party-role-assignment-cloture.md` (outils : 28/132,
phpstan/deptrac/phpcpd OK).
