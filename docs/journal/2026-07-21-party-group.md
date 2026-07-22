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
