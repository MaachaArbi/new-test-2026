## Reprise à froid

Journal — 2026-07-21 — Clôture organization_identity + office.
`PartyAccountOrganizationIdentity` + `PartyAccountOffice` sont **clos et validés** (Domain + Application + Infrastructure + bootstrap agence + corrections). Détail livré :…
`PartyAccountOrganizationIdentity` + `PartyAccountOffice` sont **clos et validés**
(Domain + Application + Infrastructure + bootstrap agence + corrections).

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Clôture organization_identity + office

## Contexte

`PartyAccountOrganizationIdentity` + `PartyAccountOffice` sont **clos et validés**
(Domain + Application + Infrastructure + bootstrap agence + corrections).
Détail livré : `2026-07-21-party-organization-identity-office.md`.

## Périmètre clos

- Domain : 2 entités (PK = `account_id`, getters only), 2 interfaces repo,
  exceptions `MustBeOrganization`, `OfficeCodeAlreadyUsed`, `NotFound`
- Application : `SetPartyAccountOrganizationIdentity` + `SetPartyAccountOffice`
  (handlers invocables ; nature=organization ; unicité `office_code` ;
  compte manquant → `PartyAccountNotFoundException`)
- Infrastructure : XML `strategy="NONE"` (PK=FK assignée), repos Doctrine
  (`$entityManager`), bootstrap `app:party:bootstrap-agency` idempotent avec
  vraies données (tax_id / website / office_code `MYGO-2023` / `TND`)
- Traductions `party_account.not_found` (en/fr/ar)
- Tests Unit + Integration PostgreSQL (round-trip, person rejetée, code
  dupliqué, compte manquant, bootstrap rejoué)

## Corrections de clôture

1. `$em` → `$entityManager` dans `DoctrinePartyAccountOfficeRepository`
2. `PartyAccountNotFoundException` remplace `InvalidArgumentException` dans
   les deux Handlers
3. Traductions + tests (unit exception, catalogue errors, intégration missing)

## Résultats finaux des 4 outils (clôture)

**phpunit** (exit 0) — 73 tests, 354 assertions

**phpstan** (exit 0) — No errors

**deptrac** (exit 0) — Violations 0 · Allowed 161 · Uncovered 63

**phpcpd** (exit 0) — No clones found
