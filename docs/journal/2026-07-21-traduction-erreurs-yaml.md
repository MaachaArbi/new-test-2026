# Journal — 2026-07-21 — Traduction erreurs YAML

## Faits

- `symfony/translation` installé ; locales `en` / `fr` / `ar` ; fallback `en`.
- Catalogues `translations/errors.{en,fr,ar}.yaml` (domain `errors`).
- `InvalidPartyAccountStateException::errorCode()` aligné sur
  `party_account.parent_account_not_allowed_for_person` (clé seed + factory).
- Test d’intégration catalogue : chaque `errorCode()` existant résout dans les 3 langues.
- Listener HTTP reporté (vague Infrastructure / Controller).
