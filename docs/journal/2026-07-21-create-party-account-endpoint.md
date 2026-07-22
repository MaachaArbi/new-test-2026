# Journal — Endpoint POST création PartyAccount

Date : 2026-07-21

## Livré

- `POST /api/v1/party-accounts` (JWT requis)
- DTO `CreatePartyAccountRequest` + validation Symfony → 422
  `{error: {code: validation_failed, message, violations[]}}`
- Application `CreatePartyAccountCommand` / `CreatePartyAccountHandler`
  (cohérent avec CreatePartyAccountGroup / Assign* — factories Domain + save)
- Réponse 201 = `PartyAccountResponse` (pas d'`id` interne) + `Location`

## Pourquoi un Handler

Même si la logique est courte (match nature → factory → save), un Handler
Application isole le Controller de Domain/Repository et reste aligné sur le
pattern déjà établi (pas d'appel factory Direct depuis le Controller).

## Règle parentAccountId

Non dupliquée dans le DTO. `person` + `parentAccountId` →
`InvalidPartyAccountStateException` (400 traduit). `organization` + parent
autorisé. Couvert aussi en unit Domain.

## Validation email unifiée (corrigé)

`#[Assert\Email]` remplacé par `#[Assert\Regex(pattern: Email::FORMAT_PATTERN)]`
sur `CreatePartyAccountRequest`. `Email::FORMAT_PATTERN` est une constante
**publique** partagée avec `Email::fromString()` — une seule règle, plus de
divergence 422 vs 400.

Cas autrefois divergents (`a@b.c`, `user@123.45.67.89`) → **422**
`validation_failed` (couverts par test HTTP dédié).

## Suite

Liste GET paginée ; update/delete si besoin.
