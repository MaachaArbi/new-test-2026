# Journal — 2026-07-21 — Revoke Function + GroupMembership

## Contexte

Réplication stricte du pattern `RevokePartyAccountRole` sur les deux
assignations restantes. Famille assignations Party (role / function /
groupmembership) désormais 100 % complete (assign + revoke).

## Faits

1. `RevokePartyAccountFunction` — Command `assignmentId` ; Handler
   findById → NotFound → Domain revoke → repo revoke
2. `RevokePartyAccountGroupMembership` — Command `membershipId` ; même flux
3. Exceptions Domain : `PartyAccountFunctionAssignmentNotFoundException`
   (`party_account_function.assignment_not_found`) ;
   `PartyAccountGroupMembershipNotFoundException`
   (`party_account_group_member.membership_not_found`)
4. Traductions en/fr/ar + `ErrorsTranslationCatalogueTest` (2 codes)
5. Integration : 3 tests × 2 (handler persist, missing, already revoked)
6. `ExceptionListener` NOT_FOUND enrichi des 2 nouveaux codes

## Qualité

**phpunit** — OK (99 tests, 520 assertions)  
**phpstan** — No errors  
**deptrac** — Violations 0 · Allowed 204 · Uncovered 85  
**phpcpd** — No clones found (seuil 10 / 20)
