## Reprise à froid

Journal — 2026-07-21 — Extraction Email vers Shared.
- `Email` + `InvalidEmailException` + `EmailType` déplacés vers Shared (même logique anti-duplication que `PublicId`).
- Type DBAL enregistré sous le nom `email` (ex-`party_email`).
- `errorCode()` : `email.invalid_format` (clés YAML `errors.*` mises à jour).

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Extraction Email vers Shared

## Faits

- `Email` + `InvalidEmailException` + `EmailType` déplacés vers Shared (même logique anti-duplication que `PublicId`).
- Type DBAL enregistré sous le nom `email` (ex-`party_email`).
- `errorCode()` : `email.invalid_format` (clés YAML `errors.*` mises à jour).
- Deptrac : règle existante `ModuleDomain` → `SharedDomain` suffit — aucun ajout.
- Domain Party : `PartyAccount` importe `App\Shared\Domain\ValueObject\Email`.
