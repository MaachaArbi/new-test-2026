# Journal — 2026-07-21 — Party organization_identity + office

## Contexte

Extensions 1-1 simples sur `party_account` (pas d’historisation valid_from/valid_to) :
`party_account_organization_identity` et `party_account_office`. PK = `account_id`
(pas de nouvel id séquentiel). Une seule règle métier réelle : nature du compte
doit être `organization` (vérifiée en Application avant création). Pour office,
unicité globale de `office_code` (même discipline que les Handlers précédents).

## Subtilité Doctrine — PK qui est aussi FK

Premier cas sur le projet où l’identifiant Doctrine est **assigné** (égal à
`party_account.id`), pas généré IDENTITY.

- Mapping XML : `<generator strategy="NONE"/>` sur `accountId`
- L’Application pose `accountId` via `::create(...)` avant `persist`/`flush`
- `EntityManager::find(Entity::class, $accountId)` fonctionne normalement
- Pas d’association Doctrine `OneToOne` déclarée : on garde deux agrégats
  indépendants liés uniquement par la valeur entière (cohérent avec le reste
  Party — pas de graphe d’objets Domain)

Colonnes audit (`created_at`, `updated_at`, `created_by`, `updated_by`) et
`country_id` (office) volontairement non mappées.

`default_currency_code` reste un `string` brut (VARCHAR(3), FK SQL vers
`ref_currency`) — pas de VO dédié tant que le Domain ref_ n’existe pas.

## Bootstrap agence

`app:party:bootstrap-agency` étendu : après le compte myGO, pose identity +
office avec les données validées (tax_id `14455455AM000`, website
`https://www.mygo.co`, is_vat_subject `false` non confirmé, office_code
`MYGO-2023`, currency `TND`). Idempotent : si le compte / l’identity / l’office
existe déjà, skip sans duplication.

## Faits

1. Domain : 2 entités, 2 interfaces repo, 3 exceptions (`MustBeOrganization`,
   `OfficeCodeAlreadyUsed`, `NotFound`)
2. Application : `SetPartyAccountOrganizationIdentity` + `SetPartyAccountOffice`
3. Infrastructure : XML strategy NONE + repos Doctrine + bootstrap
4. Tests Unit (entités + exceptions) + Integration PostgreSQL
5. Corrections clôture : `$entityManager` ; `PartyAccountNotFoundException` +
   traductions en/fr/ar — voir
   `2026-07-21-party-organization-identity-office-cloture.md`

## Résultats des 4 outils (avant corrections clôture)

**phpunit** — OK (71 tests, 343 assertions)  
**phpstan** — No errors  
**deptrac** — Violations 0 · Allowed 158 · Uncovered 63  
**phpcpd** — No clones found

Résultats finaux après corrections : journal de clôture.
