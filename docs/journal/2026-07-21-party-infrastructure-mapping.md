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
