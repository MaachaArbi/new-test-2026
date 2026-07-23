## Reprise à froid

Journal — 2026-07-21 — CoreCredential (Domain).
Premier agrégat du module Core (`core_credential` schéma V1.1). Scope basique uniquement : multi-provider auth découplée du CRM. Session / MFA / tentatives (`core_session`, `core_mfa_totp`, `core_auth_attempt`,…
Premier agrégat du module Core (`core_credential` schéma V1.1). Scope
basique uniquement : multi-provider auth découplée du CRM. Session / MFA /

## Origine

```
# TASK — Module Core : CoreCredential (Domain uniquement)

## Lecture obligatoire
1. reference/schemas/schema-core-identity-v1.sql (V1.1 — SEULE version déjà
   importée en base ; provider est VARCHAR(30) libre, PAS de FK, PAS de
   failed_login_count/locked_until — ces colonnes viennent d'une réouverture
   ultérieure du module, PAS ENCORE importée, ne pas les mapper)
2. reference/backend-cadrage/02-backend-module-index.md : Core scope = V1.1
   basique uniquement. Session/MFA/tentatives (core_session, core_mfa_totp,
   core_auth_attempt, party_role_security_policy) sont explicitement hors
   périmètre de ce prompt et des suivants proches — module distinct, plus tard.
3. Le pattern déjà validé sur PartyAccount (factories statiques, constructeur
   privé, PublicId partagé, DomainException avec contexte)

## Décision de conception imposée (ne pas dévier)
`provider` est un ENUM PHP natif, PAS un VO string ouvert (contrairement à
PartyRoleCode/PartyFunctionCode/PartyAccountGroupTypeCode). Raison : ajouter un
nouveau provider OAuth nécessite toujours du code neuf (config, flow de
redirection) — ce n'est pas un référentiel de pure donnée. Valeurs déjà
documentées dans le schéma : local, google, facebook, api_key, sso_interne.

## Fichiers à créer

src/Modules/Core/Domain/ValueObject/
└── CredentialProvider.php          — enum PHP : Local, Google, Facebook,
                                       ApiKey, SsoInterne (valeurs string
                                       correspondant exactement aux colonnes
                                       DB existantes : 'local', 'google',
                                       'facebook', 'api_key', 'sso_interne')

src/Modules/Core/Domain/Security/
└── PasswordHasherInterface.php     — hash(string $plainPassword): string ;
                                       verify(string $plainPassword, string
                                       $hash): bool. Interface Domain PURE
                                       (aucune dépendance Symfony dedans),
                                       implémentation Infrastructure (Symfony
                                       PasswordHasher) prévue pour la vague
                                       suivante, PAS dans ce prompt.

src/Modules/Core/Domain/Entity/
└── CoreCredential.php              — agrégat. Constructeur privé.
                                       Factories : createLocal(accountId,
                                       passwordHash: string, isPrimary: bool):
                                       self — passwordHash est déjà hashé en
                                       amont (Application), le Domain ne hash
                                       jamais rien lui-même, il stocke juste
                                       la string opaque.
                                       createOAuth(accountId, provider,
                                       providerUserId: string, isPrimary:
                                       bool): self — provider ne doit JAMAIS
                                       être Local ici (sinon lever une
                                       exception dédiée).
                                       Règle du schéma à respecter : provider
                                       Local => password_hash renseigné,
                                       provider_user_id NULL. Provider != Local
                                       => provider_user_id renseigné,
                                       password_hash NULL. Faire respecter ça
                                       par construction (deux factories
                                       séparées qui ne permettent pas l'état
                                       invalide), pas par une validation a
                                       posteriori.
                                       Méthodes : disable(), enable(),
                                       markAsPrimary(), recordLogin() (met à
                                       jour last_login_at).

src/Modules/Core/Domain/Repository/
└── CoreCredentialRepositoryInterface.php
                                     — findById(int $id): ?self,
                                       findByProviderIdentity(CredentialProvider,
                                       string $providerUserId): ?self,
                                       findActiveByAccountId(int $accountId):
                                       array (un compte peut avoir plusieurs
                                       credentials), save(). Pas de delete()
                                       générique (soft delete existant sur
                                       cette table — deleted_at — mais aucune
                                       méthode de suppression n'est demandée
                                       dans ce prompt ; si besoin apparaît,
                                       ARRÊTE-TOI et signale plutôt que
                                       d'improviser un disable() qui ferait
                                       aussi office de delete).

src/Modules/Core/Domain/Exception/
├── InvalidCoreCredentialStateException.php  — ex: createOAuth appelé avec
│                                                provider=Local
└── (autres si un cas d'erreur Domain réel apparaît à l'écriture)

## Tests
tests/Unit/Modules/Core/Domain/ — createLocal OK (password_hash renseigné,
provider_user_id null), createOAuth OK (inverse), createOAuth avec
provider=Local rejeté, disable/enable, markAsPrimary, recordLogin met à jour
la date. Zéro dépendance framework, zéro DB, < 0.1s total.

## Documentation
- docs/journal/2026-07-2X-core-credential-domain.md
- docs/STATUS.md : Core — "CoreCredential Domain fait (pas encore
  Infrastructure). Session/MFA explicitement hors périmètre."
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — CoreCredential (Domain)

## Contexte

Premier agrégat du module Core (`core_credential` schéma V1.1). Scope
basique uniquement : multi-provider auth découplée du CRM. Session / MFA /
tentatives (`core_session`, `core_mfa_totp`, `core_auth_attempt`,
`party_role_security_policy`) explicitement hors périmètre.

## Décision de conception

`CredentialProvider` est un **enum PHP natif** (Local, Google, Facebook,
ApiKey, SsoInterne), pas un VO string ouvert. Raison : ajouter un provider
OAuth exige toujours du code neuf (config, flow) — ce n'est pas un
référentiel de pure donnée. Valeurs = colonnes DB exactes.

## Faits

- `CredentialProvider`, `PasswordHasherInterface` (port Domain pur)
- `CoreCredential` : factories `createLocal` / `createOAuth` (invariants
  password_hash ↔ provider_user_id par construction) ; `disable` /
  `enable` / `markAsPrimary` / `recordLogin`
- Repository : `findById`, `findByProviderIdentity`,
  `findActiveByAccountId`, `save` — pas de `delete()` (soft delete
  `deleted_at` existant, méthode non demandée)
- Exception : `InvalidCoreCredentialStateException` si `createOAuth` avec
  Local
- Pas de `PublicId` : la table n'en a pas
- Pas de mapping `failed_login_count` / `locked_until` (réouverture
  ultérieure, non importée)
- Infrastructure (Doctrine + Symfony PasswordHasher) : vague suivante

## Qualité

phpstan / deptrac / phpcpd / phpunit OK après ajout.
Seuil phpcpd recalibré (`--min-lines 10 --min-tokens 20`) — voir
`docs/decisions/2026-07-21-phpcpd-seuil-ajuste.md`. Contournements
Domain / Party retirés.
