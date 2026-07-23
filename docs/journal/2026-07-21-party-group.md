## Reprise à froid

Journal — 2026-07-21 — PartyAccountGroup + PartyAccountGroupMembership.
Fermeture de la famille d’assignations Party : groupes nommés (`party_account_group`) + appartenances historisées (`party_account_group_member`). Schéma déjà importé (`party-account-group-extension.diff`) ; type…
Fermeture de la famille d’assignations Party : groupes nommés (`party_account_group`)
+ appartenances historisées (`party_account_group_member`). Schéma déjà importé

## Origine

```
# TASK — Module Party : PartyAccountGroup + PartyAccountGroupMembership

## Lecture obligatoire
1. reference/schemas/party-account-group-extension.diff (party_account_group_type,
   party_account_group, party_account_group_member — DÉJÀ importé en base, la ligne
   'commercial' existe déjà dans party_account_group_type)
2. docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md — party_account_group_type
   est le 3ème cas de VO string ouvert annoncé comme déclencheur. NE PAS extraire
   de base commune dans ce prompt. Signaler explicitement dans le journal que ce
   3ème cas est atteint, avec les 3 implémentations mises côte à côte pour que la
   décision se prenne sur pièces au retour — ne pas trancher seul.
3. Le pattern déjà validé sur PartyAccountRoleAssignment (répliquer pour
   PartyAccountGroupMembership)

## Différences structurelles à respecter (pas une réplication pure)
- party_account_group N'EST PAS un simple code : c'est une entité avec id+public_id,
  name (saisi), group_type_code. Cycle de vie : create, rename. Contrainte
  d'unicité (group_type_code, name) — vérification explicite en Application avant
  insert (même logique que "pas de doublon actif", mais ici sur l'unicité du nom,
  pas une assignation).
- party_account_group_member (l'appartenance) N'A PAS d'exclusivité par type :
  seule la paire (account_id, group_id) doit être unique en actif — deux groupes
  différents du même type peuvent coexister activement pour le même compte. Ne
  PAS reproduire une logique de triplet façon Function ici, ce serait une règle
  inventée qui n'existe pas dans le schéma.

## Fichiers à créer

### PartyAccountGroupType (VO — 3ème cas, cf. point 2 ci-dessus)
src/Modules/Party/Domain/ValueObject/PartyAccountGroupTypeCode.php — même structure
que PartyRoleCode/PartyFunctionCode (trim, rejet vide, rejet >30 car.)

### PartyAccountGroup (nouvel agrégat, pas une réplication)
src/Modules/Party/Domain/Entity/PartyAccountGroup.php
  - create(groupTypeCode, name): self (public_id généré, comme PartyAccount)
  - rename(string $newName): void
  - Getters : id, publicId, groupTypeCode, name

src/Modules/Party/Domain/Repository/PartyAccountGroupRepositoryInterface.php
  - findById(int $id): ?PartyAccountGroup
  - existsByTypeAndName(PartyAccountGroupTypeCode $type, string $name): bool
  - save(PartyAccountGroup $group): void

src/Modules/Party/Domain/Exception/
  - InvalidPartyAccountGroupTypeCodeException.php (même forme que les précédentes)
  - PartyAccountGroupNameAlreadyUsedException.php (already_used, contexte
    group_type_code + name)

src/Modules/Party/Application/CreatePartyAccountGroup/
  - CreatePartyAccountGroupCommand.php
  - CreatePartyAccountGroupHandler.php — vérifie existsByTypeAndName() AVANT
    create(), même discipline que les Handlers précédents

### PartyAccountGroupMembership (réplique le pattern PartyAccountRoleAssignment)
src/Modules/Party/Domain/Entity/PartyAccountGroupMembership.php
  - assign(accountId, groupId, createdBy): self ; revoke() (rejette double révocation) ;
    isActive() ; validFrom()/validTo() en simples return (cf. correction faite sur
    Function — pas de logique conditionnelle superflue)

src/Modules/Party/Domain/Repository/PartyAccountGroupMembershipRepositoryInterface.php
  - assign(), revoke() (docblock précondition identique, texte exact repris),
    findById(), hasActiveMembership(accountId, groupId): bool — PAIRE, pas triplet

src/Modules/Party/Domain/Exception/
  - InvalidPartyAccountGroupMembershipException.php (already_revoked)
  - PartyAccountGroupMembershipAlreadyActiveException.php (already_active,
    contexte account_id + group_id)

src/Modules/Party/Application/AssignPartyAccountGroupMembership/
  - AssignPartyAccountGroupMembershipCommand.php
  - AssignPartyAccountGroupMembershipHandler.php — vérifie hasActiveMembership()
    sur la PAIRE avant assign()

### Infrastructure (les deux agrégats)
config/doctrine/mappings/PartyAccountGroup.orm.xml
config/doctrine/mappings/PartyAccountGroupMembership.orm.xml
src/Modules/Party/Infrastructure/Doctrine/Type/PartyAccountGroupTypeCodeType.php
src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountGroupRepository.php
src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountGroupMembershipRepository.php
(enregistrer le nouveau type Doctrine + les deux mappings selon le pattern déjà en place)

## Tests
Unit : PartyAccountGroupTypeCodeTest, PartyAccountGroupTest (create, rename),
PartyAccountGroupMembershipTest (même structure que RoleAssignmentTest)

Integration (PostgreSQL réel) :
- PartyAccountGroupPersistenceTest : round-trip, rename persisté, création avec
  nom déjà pris dans le même type → rejetée avant SQL, même nom mais type
  différent → autorisée (pas de collision cross-type)
- PartyAccountGroupMembershipPersistenceTest : round-trip, revoke, LE test clé :
  même compte assigné à DEUX groupes différents de même type 'commercial'
  simultanément → autorisé (vérifié par lookup des deux, pas de count global) ;
  doublon réel (même account_id + même group_id) → rejeté avant SQL ;
  réassignation après revoke

## Documentation
- docs/journal/2026-07-2X-party-group.md — inclure explicitement la section
  "3ème cas VO string ouvert atteint" avec les 3 implémentations listées côte à
  côte pour revue
- docs/STATUS.md : famille assignations Party complète (rôle, fonction, groupe)
- docs/backlog/todo.md mis à jour : organization_identity/office, Core, Controller,
  RevokePartyAccountRole restent ; ajouter "décision extraction VO ouvert : 3ème
  cas atteint, en attente de validation"

Relance phpstan/deptrac/phpcpd/phpunit (Unit + Integration réel). Colle le contenu
intégral de tous les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — PartyAccountGroup + PartyAccountGroupMembership

## Contexte

Fermeture de la famille d’assignations Party : groupes nommés (`party_account_group`)
+ appartenances historisées (`party_account_group_member`). Schéma déjà importé
(`party-account-group-extension.diff`) ; type référentiel `commercial` peuplé.

## Différences structurelles respectées

- **Group** : agrégat avec `id` + `public_id` + `name` + `group_type_code` ;
  `create` / `rename` ; unicité `(group_type_code, name)` vérifiée en Application
  via `existsByTypeAndName()` avant insert.
- **Membership** : pattern Role (paire `account_id` + `group_id` active) — **pas**
  de triplet, **pas** d’exclusivité par type (deux groupes `commercial` actifs
  pour le même compte autorisés).

## 3ème cas VO string ouvert atteint

Déclencheur de `docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md`.
**Pas d’extraction** dans ce prompt — les 3 implémentations côte à côte pour
revue / décision au retour.

### 1. `PartyRoleCode` (1er cas)

- `private const MAX_LENGTH = 30`
- propriété promue `private string $value`
- `fromString` : `trim` → `=== ''` → `strlen > MAX` → `new self`
- exception : `InvalidPartyRoleCodeException`

### 2. `PartyFunctionCode` (2e cas)

- `private const int MAX_LENGTH = 30`
- champ `private string $value` + ctor assigné
- `fromString` délègue à `normalize()` (`length < 1` / `> MAX`)
- `toString()` placé avant `fromString`
- exception : `InvalidPartyFunctionCodeException`

### 3. `PartyAccountGroupTypeCode` (3e cas — ce prompt)

- `private const CEILING = 30`
- propriété promue `private string $code`
- `fromString` : `trim` + `strlen` + **`match (true)`** avec throws / `new self`
- `toString()` avant `fromString`
- exception : `InvalidPartyAccountGroupTypeCodeException`

## Faits

1. Domain Group + Membership + exceptions + interfaces repo
2. Application `CreatePartyAccountGroup` + `AssignPartyAccountGroupMembership`
   (handlers invocables, vérif avant écriture)
3. Infrastructure XML + type DBAL `party_account_group_type_code` + repos Doctrine
4. Tests Unit + Integration PostgreSQL (cross-type name OK ; deux groupes
   commercial simultanés OK ; doublons rejetés avant SQL)

## Résultats des 4 outils

**phpunit** — OK (60 tests, 285 assertions)  
**phpstan** — No errors  
**deptrac** — Violations 0 · Allowed 120 · Uncovered 61  
**phpcpd** — No clones found
