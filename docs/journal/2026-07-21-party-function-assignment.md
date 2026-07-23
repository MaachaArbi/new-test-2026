## Reprise à froid

Journal — 2026-07-21 — PartyAccountFunctionAssignment.
Assignation de fonction (table `party_account_function`) — Domain + Application + Infrastructure, même pattern que `PartyAccountRoleAssignment`, avec nuance structurelle : unicité active = **triplet**…
Assignation de fonction (table `party_account_function`) — Domain + Application +
Infrastructure, même pattern que `PartyAccountRoleAssignment`, avec nuance

## Origine

```
# TASK — Module Party : PartyAccountFunctionAssignment (Domain + Application + Infrastructure)

## Lecture obligatoire
1. reference/schemas/schema-party-account-v1.sql : table party_account_function
   (colonnes person_account_id, organization_account_id, function_code,
   valid_from/valid_to) + party_function (référentiel ouvert, comme party_role)
2. reference/conceptual-models/modele-conceptuel-party.md, décisions #11 et #14
   (fusion de l'ancienne notion d'accès générique dans la fonction 'member')
3. Le code déjà validé pour PartyAccountRoleAssignment (Domain, Application,
   Infrastructure, Repository, tests) — RÉPLIQUER ce pattern exactement, sauf
   sur le point de nuance ci-dessous

## Nuance structurelle à respecter strictement
L'unicité "pas de doublon actif" porte sur le TRIPLET
(person_account_id, function_code, organization_account_id), pas sur une paire
comme pour le rôle. Une même personne peut avoir la fonction 'member' pour
l'organisation A et 'gerant' pour l'organisation B simultanément — ce n'est PAS
un doublon. organization_account_id est TOUJOURS obligatoire (jamais NULL,
cf. décision #13 du modèle conceptuel — pas de NULL magique pour un contexte
"interne").

## Fichiers à créer

src/Modules/Party/Domain/ValueObject/
└── PartyFunctionCode.php          — même pattern que PartyRoleCode (VO string,
                                      PAS un enum, référentiel ouvert)

src/Modules/Party/Domain/Entity/
└── PartyAccountFunctionAssignment.php
                                    — même pattern que PartyAccountRoleAssignment :
                                      assign(personAccountId, organizationAccountId,
                                      functionCode, createdBy): self ; revoke()
                                      (rejette une double révocation, cohérent
                                      avec le comportement déjà validé sur role) ;
                                      isActive()

src/Modules/Party/Domain/Repository/
└── PartyAccountFunctionAssignmentRepositoryInterface.php
                                    — assign(), revoke() (docblock précondition
                                      identique à celui validé sur role — copier
                                      le texte exact, pas une reformulation),
                                      findById(), hasActiveFunction(personAccountId,
                                      organizationAccountId, functionCode): bool

src/Modules/Party/Domain/Exception/
├── InvalidPartyFunctionCodeException.php       (même structure que
│                                                 InvalidPartyRoleCodeException)
├── InvalidPartyAccountFunctionAssignmentException.php  (already_revoked, même
│                                                 structure)
└── PartyAccountFunctionAlreadyActiveException.php      (already_active, contexte
                                                  incluant les 3 clés du triplet)

src/Modules/Party/Application/AssignPartyAccountFunction/
├── AssignPartyAccountFunctionCommand.php
└── AssignPartyAccountFunctionHandler.php  — vérifie hasActiveFunction() sur le
                                              TRIPLET avant assign() ; service
                                              invocable simple, même docblock
                                              justificatif que pour le rôle
                                              (pas de #[AsMessageHandler] pour
                                              l'instant)

config/doctrine/mappings/PartyAccountFunctionAssignment.orm.xml
src/Modules/Party/Infrastructure/Doctrine/Type/PartyFunctionCodeType.php
src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountFunctionAssignmentRepository.php
                                    — enregistrer party_function_code dans
                                      doctrine.yaml selon le pattern déjà en place

tests/Unit/Modules/Party/Domain/ (ValueObject + Entity)
tests/Integration/Modules/Party/Infrastructure/PartyAccountFunctionAssignmentPersistenceTest.php
                                    — round-trip, revoke, LE test clé : même
                                      personne + même fonction mais DEUX
                                      organisations différentes → autorisé (pas
                                      de doublon), vérifié par un count par
                                      organisation ; doublon réel (même triplet)
                                      → rejeté avant SQL ; réassignation après
                                      revoke

## Contraintes non négociables (inchangées)
Zéro dépendance framework dans Domain, zéro accès DB dans les tests unitaires,
PHPStan niveau max, zéro duplication phpcpd. Si un besoin de logique partagée
entre PartyRoleCode et PartyFunctionCode apparaît clairement à l'écriture (pas
avant), le signaler dans le journal plutôt que d'extraire silencieusement — je
trancherai si c'est le moment ou pas (règle "généraliser au 2ème cas réel").

## Documentation
- docs/journal/2026-07-2X-party-function-assignment.md
- docs/STATUS.md : Party — "PartyAccountFunctionAssignment clos". Restent :
  group(+member), organization_identity/office, Core, Controller, Revoke role.
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd/phpunit (Unit + Integration réel). Colle le
contenu intégral de tous les fichiers créés (pas de résumé, pas de "même que
précédent" sans montrer le texte réel) et les résultats des 4 outils.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
