## Reprise à froid

Journal — 2026-07-21 — Party Infrastructure (mapping + repository + bootstrap).
Couche Infrastructure pour l’agrégat `party_account` uniquement (ADR-002 XML, ADR-018 BIGINT + public_id). Puis extraction Email vers Shared (anti-duplication).
Couche Infrastructure pour l’agrégat `party_account` uniquement (ADR-002 XML,
ADR-018 BIGINT + public_id). Puis extraction Email vers Shared (anti-duplication).

## Origine

```
# TASK — Module Party : couche Infrastructure (mapping Doctrine + Repository + bootstrap)

## Lecture obligatoire
1. reference/backend-cadrage/01-backend-architecture-decisions.md (ADR-002 : mapping
   XML uniquement, zéro annotation Doctrine ; ADR-018 : BIGINT + public_id)
2. reference/schemas/schema-party-account-v1.sql (colonnes réelles de party_account,
   déjà importées en base)
3. Le code Domain déjà écrit : src/Modules/Party/Domain/ (ne pas le modifier sauf
   si un besoin de mapping l'exige — dans ce cas, ARRÊTE-TOI et signale-le avant
   de toucher au Domain)

## Portée
Uniquement party_account (table pivot). PAS party_account_organization_identity,
PAS party_account_office (extensions pas encore modélisées côté Domain — laissées
pour plus tard, à noter dans todo.md).

## 1. Mapping Doctrine XML
config/doctrine/Party.PartyAccount.orm.xml — mappe UNIQUEMENT les champs déjà
présents dans l'entité Domain PartyAccount (id, publicId, nature, displayName,
email, parentAccountId, isDisabled, isProspect, isDisputed). Les autres colonnes
réelles de la table (phone_primary, country_id, logo_url, timestamps, deleted_at,
created_by/updated_by...) sont TOUTES nullable ou ont un défaut côté PostgreSQL —
ne pas les mapper pour l'instant, ce n'est pas un oubli mais un scope volontaire,
à documenter comme tel dans le journal.

- id : mapping BIGINT, stratégie IDENTITY
- publicId : colonne public_id — Value Object PublicId nécessite un Doctrine Type
  custom (App\Shared\Infrastructure\Doctrine\Type\PublicIdType) faisant la
  conversion PublicId <-> string
- email : colonne email — même principe, EmailType custom (nullable)
- nature : PartyAccountNature — enum PHP natif, utiliser le support enum natif
  Doctrine (pas de Type custom nécessaire si la version Doctrine installée le
  supporte ; vérifier et le confirmer dans le journal)
- Le constructeur de PartyAccount est privé : le mapping XML doit cibler les
  propriétés directement (reflection), PAS passer par le constructeur ni ajouter
  de setters publics au Domain

## 2. Repository
src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountRepository.php
implémentant PartyAccountRepositoryInterface (findById via EntityManager,
save via persist+flush).

## 3. Commande de bootstrap agence (idempotente)
src/Modules/Party/Infrastructure/Command/BootstrapAgencyAccountCommand.php
(bin/console app:party:bootstrap-agency)

Données (en dur dans la commande, ce sont des données de bootstrap uniques, pas
une donnée métier récurrente) :
- display_name: "myGO"
- email: "booking@mygo.pro"
- phone_primary: "+216 58 511 535"  [si le champ n'est pas encore mappé (cf. point
  1), ignorer ce champ pour cette commande, ne PAS l'ajouter au mapping juste pour
  ça]
- nature: organization

AVANT de créer la ligne : vérifier qu'aucun party_account avec ce display_name
n'existe déjà (requête simple), pour que la commande soit rejouable sans doublon.
Si trouvé, ne rien faire et l'indiquer en sortie console.

## 4. Tests d'intégration (PostgreSQL réel, tests/Integration/, jamais SQLite)
- Round-trip complet : créer un PartyAccount via le Domain, save(), findById(),
  vérifier que chaque champ mappé revient identique (surtout publicId et nature)
- La commande de bootstrap : exécution réelle contre la base de test, vérifier la
  ligne créée, puis ré-exécution, vérifier qu'aucun doublon n'est créé

## Documentation
- docs/journal/2026-07-2X-party-infrastructure-mapping.md
- docs/STATUS.md : Party → "Domain + Infrastructure (party_account) prêts,
  bootstrap agence exécutable. Restent : assignations rôle/fonction/groupe,
  organization_identity/office, Core, Controller."
- docs/backlog/todo.md : ajouter party_account_organization_identity et
  party_account_office comme extensions à modéliser (Domain + Infra)

Relance phpstan/deptrac/phpcpd/phpunit (Unit + Integration). Colle le contenu de
tous les fichiers créés et les résultats.
Ramène-moi ça, et exécute aussi la commande de bootstrap une fois validée — je veux voir la confirmation qu'une vraie ligne existe en base avant qu'on avance.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Party Infrastructure (mapping + repository + bootstrap)

## Contexte

Couche Infrastructure pour l’agrégat `party_account` uniquement (ADR-002 XML,
ADR-018 BIGINT + public_id). Puis extraction Email vers Shared (anti-duplication).

## Ordre des faits

1. **Mapping XML** `config/doctrine/Party.PartyAccount.orm.xml` (hardlink
   `mappings/PartyAccount.orm.xml` pour SimplifiedXmlDriver). Champs Domain
   uniquement ; colonnes table non mappées volontairement (`phone_primary`,
   timestamps, audit, etc.). Instantiation via reflection (ctor privé Domain).
2. **Types Doctrine** : `PublicIdType` (`public_id`) Shared ; `EmailType` d’abord
   sous Party (`party_email`), puis Shared (`email`) après extraction.
   `PartyAccountNature` : enum PHP natif + `enum-type` XML (pas de Type custom).
3. **`DoctrinePartyAccountRepository`** : `findById` / `save` (persist+flush).
4. **`BootstrapAgencyAccountCommand`** (`app:party:bootstrap-agency`) : myGO /
   booking@mygo.pro / organization ; idempotente sur `display_name` ;
   `phone_primary` ignoré (non mappé).
5. **Ligne réelle en base** : `id=9`, `public_id=2d100ddf-72ab-44c3-a5be-3061db669e71`,
   nature `organization`, display_name `myGO`, email `booking@mygo.pro`.
6. **Extraction Email vers Shared** : `Email` + `InvalidEmailException` +
   `EmailType` déplacés (`App\Shared\…`) — raison : validation de format
   générique, réutilisable multi-modules (Core/Booking), pas spécifique à Party.
   `errorCode` → `email.invalid_format` ; type DBAL → `email`.
   Deptrac : règle `ModuleDomain` → `SharedDomain` déjà suffisante.

## Résultats finaux des 4 outils (post-extraction Email)

- **phpunit** : OK (16 tests, 79 assertions) — Unit + Integration PostgreSQL
- **phpstan** : No errors
- **deptrac** : Violations 0
- **phpcpd** : No clones found
