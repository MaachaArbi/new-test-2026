## Reprise à froid

Journal — 2026-07-21 — CoreCredential (Infrastructure).
Couche Infrastructure pour `core_credential` V1.1 : mapping Doctrine XML, repository, adapter Symfony PasswordHasher. Session / MFA / tentatives restent hors périmètre.
Couche Infrastructure pour `core_credential` V1.1 : mapping Doctrine XML,
repository, adapter Symfony PasswordHasher. Session / MFA / tentatives restent

## Origine

```
voilà le prompt au complet
# TASK — Module Core : CoreCredential Infrastructure (Doctrine + PasswordHasher)

## Lecture obligatoire
1. src/Modules/Core/Domain/ (déjà validé — Entity, Repository interface, VO,
   PasswordHasherInterface, exceptions)
2. reference/schemas/schema-core-identity-v1.sql (colonnes réelles V1.1 uniquement)
3. Le pattern déjà en place : config/doctrine/mappings/*.orm.xml,
   config/packages/doctrine.yaml (section dbal.types + orm.mappings),
   config/services.yaml (alias Repository)

## 1. Mapping Doctrine XML
config/doctrine/mappings/CoreCredential.orm.xml — mapper uniquement les champs
du Domain (id, accountId, provider, providerUserId, passwordHash, isPrimary,
isEnabled, lastLoginAt). Colonnes non mappées (scope volontaire, cohérent avec
le reste du projet) : created_at, updated_at, deleted_at.
- provider : enum PHP natif CredentialProvider — même mécanisme enum-type déjà
  utilisé sur PartyAccountNature, pas de Type custom nécessaire
- id : BIGINT IDENTITY (comme tous les agrégats à cycle de vie propre)
Ajouter le mapping Core dans doctrine.yaml (nouveau bloc orm.mappings, comme
le bloc Party existant — même structure, prefix vers
App\Modules\Core\Domain\Entity)

## 2. PasswordHasherInterface — implémentation Infrastructure
src/Modules/Core/Infrastructure/Security/SymfonyPasswordHasher.php
Implémente PasswordHasherInterface en s'appuyant sur le composant Symfony
PasswordHasher (déjà disponible dans le skeleton Symfony 7.4 — vérifier, sinon
composer require symfony/password-hasher). Utiliser l'algorithme par défaut
recommandé de Symfony (auto, qui résout vers Argon2id ou Bcrypt selon
disponibilité serveur — ne pas forcer un algorithme en dur, laisser Symfony
décider selon son propre security.yaml). Si security.yaml n'existe pas encore
dans ce projet (probable, aucun Controller/firewall configuré à ce stade),
configurer uniquement la section password_hashers minimale nécessaire, rien
d'autre (pas de firewall, pas de providers — hors périmètre, prématuré).

## 3. Repository
src/Modules/Core/Infrastructure/Persistence/DoctrineCoreCredentialRepository.php
implémentant CoreCredentialRepositoryInterface. Nommage cohérent :
$entityManager (jamais $em, jamais $doctrine — cf. incident de cohérence
déjà rencontré sur Party, à ne pas répéter).

## 4. Alias services.yaml
Ajouter les deux alias (CoreCredentialRepositoryInterface,
PasswordHasherInterface) selon le pattern déjà en place, public: true.

## 5. Tests d'intégration (PostgreSQL réel)
- Round-trip createLocal : persist, clear, findById, vérifier passwordHash
  identique (la string opaque, pas le mot de passe en clair — pas de hash réel
  nécessaire dans ce test, juste vérifier la persistence de la string)
- Round-trip createOAuth : idem, provider + providerUserId
- findByProviderIdentity : retrouve bien un credential OAuth par (provider,
  providerUserId)
- findActiveByAccountId : retourne la liste, vérifie qu'un compte avec
  plusieurs credentials (local + google par exemple) retourne bien les deux
- SymfonyPasswordHasher (test dédié, séparé, pas besoin de PostgreSQL) :
  hash() produit une string différente du mot de passe en clair, verify()
  retourne true pour le bon mot de passe et false pour un mauvais — vérifier
  aussi que deux hash() successifs du même mot de passe produisent des
  résultats DIFFÉRENTS (salage), ce qui est le comportement de sécurité
  attendu, pas un bug

## Documentation
- docs/journal/2026-07-2X-core-credential-infrastructure.md
- docs/STATUS.md : Core — "CoreCredential complet (Domain + Infrastructure +
  hashing). Reste hors périmètre : session/MFA/tentatives (module distinct)."
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd (nouveau seuil)/phpunit (Unit + Integration).
Colle le contenu intégral de tous les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
