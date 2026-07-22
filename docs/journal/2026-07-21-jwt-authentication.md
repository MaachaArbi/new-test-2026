# Journal — Authentification JWT (login)

Date : 2026-07-21

## Livré

- Bundle `lexik/jwt-authentication-bundle` + clés RSA hors Git (`config/jwt/*.pem`)
- `SecurityUser` + `CoreCredentialUserProvider` + enrichissement payload
  (`public_id` uniquement — pas d'`account_id` interne, ADR-018)
- `POST /api/v1/auth/login` → `{token}` ; `/api/v1/*` protégé
- `PartyAccountRepository::findByEmail()`
- Commande idempotente `app:core:bootstrap-admin-credential <password>`
- Tests intégration login + endpoint protégé ; GetPartyAccount adapté au JWT

## Architecture

Domain `CoreCredential` inchangé (pas de `UserInterface`). Adapter Infrastructure
uniquement — cf. `docs/decisions/2026-07-21-jwt-lexik.md`.

## Suite

CORS, endpoints d'écriture Party.
