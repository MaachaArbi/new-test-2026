# Journal — Extraction OpenReferentialCode

Date : 2026-07-21

## Contexte

Le 3ᵉ VO string sur référentiel ouvert (`PartyAccountGroupTypeCode`) a confirmé
le déclencheur de la décision
`docs/decisions/2026-07-21-vo-string-ouvert-pas-extraction.md`.

## Changement

- Création de `App\Shared\Domain\ValueObject\OpenReferentialCode` (trim, vide,
  longueur max via `maxLength()`, exceptions via hooks de sous-classe).
- `PartyRoleCode`, `PartyFunctionCode`, `PartyAccountGroupTypeCode` héritent
  et ne gardent que maxLength = 30 + leurs exceptions Domain existantes.

## Non-changé

- Aucun errorCode, aucune traduction, aucun test VO modifié.
- Comportement observable identique (réduction de duplication uniquement).

## Qualité

phpstan / deptrac / phpcpd / phpunit — compte de tests/assertions inchangé.
