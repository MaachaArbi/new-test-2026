# Journal — 2026-07-21 — PartyAccountRoleAssignment (Domain)

## Contexte

Premier agrégat d'assignation Party : `party_account_role` (décision #11 —
historisation via `valid_to`, pas d'UPDATE sur le contenu). Function et group
suivront après validation.

## Piège évité

`party_role` est un référentiel **ouvert** (table, pas ENUM) — `PartyRoleCode`
est un VO string (non vide, max 30) **pas** un enum PHP (contrairement à
`PartyAccountNature`).

## Faits

- `PartyRoleCode`, `PartyAccountRoleAssignment` (`assign` / `revoke` / `isActive`)
- Repository : `assign` + `revoke` uniquement (pas de delete / update générique)
- Exceptions Domain : `InvalidPartyRoleCodeException`,
  `InvalidPartyAccountRoleAssignmentException` (double revoke)
- Pas de `PublicId` : la table `party_account_role` n'en a pas
- Pas de prévention de doublon actif (lecture base → Infra/Application)
