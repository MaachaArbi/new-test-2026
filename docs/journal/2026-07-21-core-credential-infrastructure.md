# Journal — 2026-07-21 — CoreCredential (Infrastructure)

## Contexte

Couche Infrastructure pour `core_credential` V1.1 : mapping Doctrine XML,
repository, adapter Symfony PasswordHasher. Session / MFA / tentatives restent
hors périmètre.

## Faits

1. **Mapping XML** `CoreCredential.orm.xml` — champs Domain uniquement ;
   `created_at` / `updated_at` / `deleted_at` non mappés. `CredentialProvider`
   via `enum-type` (comme `PartyAccountNature`).
2. **doctrine.yaml** — bloc `orm.mappings.Core` (prefix
   `App\Modules\Core\Domain\Entity`, dir `mappings/Core/`). Party déplacé
   vers `mappings/Party/` : un dir partagé faisait préfixer à tort les XML
   (SimplifiedXmlDriver).
3. **`DoctrineCoreCredentialRepository`** — `$entityManager` ; `findById`,
   `findByProviderIdentity`, `findActiveByAccountId` (filtre `isEnabled`),
   `save`.
4. **`SymfonyPasswordHasher`** — port Domain ; algorithm `auto` via
   `security.yaml` (password_hashers uniquement ; firewall `security: false`
   imposé par SecurityBundle, pas d'auth HTTP).
5. **Alias** `services.yaml` : repo + `PasswordHasherInterface`, `public: true`.
6. **Tests** : round-trips Local/OAuth, findByProviderIdentity,
   findActiveByAccountId (multi + disabled exclu) ; hasher (hash ≠ plain,
   verify, salage → deux hash distincts).

## Qualité

phpstan / deptrac / phpcpd (seuil 10/20) / phpunit OK.
