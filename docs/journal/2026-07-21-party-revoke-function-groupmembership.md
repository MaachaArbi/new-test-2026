## Reprise à froid

Journal — 2026-07-21 — Revoke Function + GroupMembership.
Réplication stricte du pattern `RevokePartyAccountRole` sur les deux assignations restantes. Famille assignations Party (role / function / groupmembership) désormais 100 % complete (assign + revoke).
Réplication stricte du pattern `RevokePartyAccountRole` sur les deux
assignations restantes. Famille assignations Party (role / function /

## Origine

```
# TASK — Module Party : RevokePartyAccountFunction + RevokePartyAccountGroupMembership

## Contexte
Même pattern que RevokePartyAccountRole (déjà validé) : findById() → Domain
revoke() → repository revoke() (même instance gérée, précondition déjà
documentée sur les deux interfaces). Deux use cases à créer, en réplication
stricte.

## RevokePartyAccountFunction
src/Modules/Party/Application/RevokePartyAccountFunction/
├── RevokePartyAccountFunctionCommand.php   — assignmentId: int
└── RevokePartyAccountFunctionHandler.php   — findById(), NotFound si absent
    (créer PartyAccountFunctionAssignmentNotFoundException, même famille),
    revoke() Domain, repository->revoke()

## RevokePartyAccountGroupMembership
src/Modules/Party/Application/RevokePartyAccountGroupMembership/
├── RevokePartyAccountGroupMembershipCommand.php   — membershipId: int
└── RevokePartyAccountGroupMembershipHandler.php   — même logique (créer
    PartyAccountGroupMembershipNotFoundException)

## Tests (Integration, PostgreSQL réel)
Pour chacun des deux, répliquer exactement les 3 tests déjà validés sur Role :
- revoke via Handler → valid_to persisté
- ID inexistant → NotFoundException (errorCode + context vérifiés)
- déjà révoqué → l'exception Domain existante remonte intacte à travers le
  Handler (errorCode + context préservés)

## Traductions (ne pas oublier, cf. incident précédent sur ce point précis)
Ajouter les 2 nouveaux errorCode dans translations/errors.{en,fr,ar}.yaml ET
dans ErrorsTranslationCatalogueTest — vérifier explicitement que les deux
nouveaux codes y figurent avant de considérer la vague terminée.

## Documentation
- docs/journal/2026-07-2X-party-revoke-function-groupmembership.md
- docs/STATUS.md : famille assignations Party 100% complète (assign + revoke
  sur les 3 : role, function, groupmembership)
- docs/backlog/todo.md : retirer les deux items, ne reste plus que Controller
  écriture + décision VO ouvert

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
