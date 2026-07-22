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
