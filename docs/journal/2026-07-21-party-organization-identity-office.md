## Reprise à froid

Journal — 2026-07-21 — Party organization_identity + office.
Extensions 1-1 simples sur `party_account` (pas d’historisation valid_from/valid_to) : `party_account_organization_identity` et `party_account_office`. PK = `account_id` (pas de nouvel id séquentiel). Une seule règle…
Extensions 1-1 simples sur `party_account` (pas d’historisation valid_from/valid_to) :
`party_account_organization_identity` et `party_account_office`. PK = `account_id`

## Origine

```
# TASK — Module Party : extensions organization_identity + office

## Lecture obligatoire
1. reference/schemas/schema-party-account-v1.sql : party_account_organization_identity
   et party_account_office (extensions 1-1, PK = account_id, pas de nouvel id
   séquentiel — la clé, c'est le compte lui-même)
2. Le code déjà validé de PartyAccount (Domain, Infrastructure, bootstrap)

## Nature du travail (différent des assignations précédentes)
Ce sont des extensions 1-1 SIMPLES, pas d'historisation (pas de valid_from/valid_to
ici), pas de vérification de doublon complexe. Une seule règle métier réelle :
une extension organization_identity/office ne peut être créée QUE pour un compte
de nature 'organization' — à vérifier explicitement en Application (lire le
PartyAccount concerné, vérifier sa nature, avant de créer l'extension).

## Fichiers à créer

### PartyAccountOrganizationIdentity
src/Modules/Party/Domain/Entity/PartyAccountOrganizationIdentity.php
  - create(accountId, taxId, tradeRegister, legalFormCode, isVatSubject, website): self
  - Tous les champs sauf accountId sont nullable (cf. schéma)
  - Getters uniquement, pas de setters — si une correction doit être possible,
    ARRÊTE-TOI et demande plutôt que de créer une méthode update() générique

src/Modules/Party/Domain/Repository/PartyAccountOrganizationIdentityRepositoryInterface.php
  - findByAccountId(int $accountId): ?self
  - save(...)

### PartyAccountOffice
src/Modules/Party/Domain/Entity/PartyAccountOffice.php
  - create(accountId, officeCode, defaultCurrencyCode): self
  - officeCode : unique globalement (uq_party_account_office_code) — vérification
    Application avant création, même discipline que les Handlers précédents
  - defaultCurrencyCode : string simple pour l'instant (VARCHAR(3), FK vers
    ref_currency — pas de VO dédié, la table ref_currency existe mais son Domain
    n'est pas encore construit ; garder simple, ne pas anticiper)

src/Modules/Party/Domain/Repository/PartyAccountOfficeRepositoryInterface.php
  - findByAccountId(int $accountId): ?self
  - existsByOfficeCode(string $code): bool
  - save(...)

### Exceptions
- PartyAccountMustBeOrganizationException (levée si on tente de créer une
  extension pour un compte de nature 'person')
- PartyAccountOfficeCodeAlreadyUsedException

### Application
src/Modules/Party/Application/SetPartyAccountOrganizationIdentity/
  - Command + Handler : charge le PartyAccount, vérifie nature=organization,
    crée l'extension

src/Modules/Party/Application/SetPartyAccountOffice/
  - Command + Handler : vérifie nature=organization + unicité officeCode, crée
    l'extension

### Infrastructure
Mapping Doctrine XML pour les deux (id du mapping = accountId, pas de colonne id
séparée — vérifier comment Doctrine gère une PK qui est aussi une FK, cas pas
encore rencontré sur ce projet, à documenter dans le journal si une subtilité
apparaît), Repository Doctrine pour les deux.

## Mise à jour du bootstrap agence
Étendre BootstrapAgencyAccountCommand (déjà existante) pour, une fois le
party_account créé, créer aussi son organization_identity et son office avec
les VRAIES données déjà validées :
  - tax_id: "14455455AM000"
  - website: "https://www.mygo.co"
  - is_vat_subject: false (valeur par défaut, non confirmée — cf. échange précédent)
  - office_code: "MYGO-2023"
  - default_currency_code: "TND"
Rendre cette extension de la commande idempotente aussi (si l'identity/office
existe déjà pour ce compte, ne rien recréer).

## Tests
Unit : les deux entités (create), les deux exceptions
Integration (PostgreSQL réel) :
- round-trip des deux extensions
- tentative de création sur un compte 'person' → rejetée
- office_code dupliqué → rejeté
- bootstrap agence ré-exécuté : vérifier que tax_id/office_code/currency sont
  bien en base, ET que rejouer la commande ne duplique rien

## Documentation
- docs/journal/2026-07-2X-party-organization-identity-office.md
- docs/STATUS.md : Party — "organization_identity + office faits, bootstrap
  agence complet avec vraies données. Restent : Core, Controller, Revoke role"
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés/modifiés (bootstrap command inclus) et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
