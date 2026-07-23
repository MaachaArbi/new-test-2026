# Journal — 2026-07-23 — Cash Management : pivot cash_session (open/close)

## Portée

`cash_session` + `cash_open_session()` + `cash_close_session()` uniquement.
Hors vague : `cash_movement`, balances/counts, `cash_validate_session`,
autres fonctions PL/pgSQL, HTTP.

## Schéma / migration

Migration `Version20260723000000` — alignée
`reference/schemas/schema-cash-management-v1.sql` (§3 + open/close).

Preuve DB (après migrate) :

```text
\d cash_session
→ colonnes holder/office/status/opened_*/closed_*/validated_*
→ Indexes : uq_cash_session_one_open_per_holder (partial WHERE open),
  idx_cash_session_office, uq_cash_session_public_id
→ Check : status_code IN (open,closed,validated) + chk_session_lifecycle

\df cash_open_session / cash_close_session
→ signatures (bigint,bigint,bigint) → bigint / (bigint,bigint) → void
```

Les fonctions sont **auto-suffisantes** (pas de dépendance à
`cash_movement` ni tables satellites).

## Pattern d'écriture (miroir PostReglementTransfer)

| Opération | Mécanisme | UnitOfWork / ORM |
|---|---|---|
| Open | `SELECT cash_open_session(...)` DBAL | Non |
| Close | `SELECT cash_close_session(...)` DBAL | Non |
| Lecture | `DbalCashSessionRepository::findById` | Non (pas de `.orm.xml`) |

`CashSession` Domain = **reconstruction lecture seule**
(`fromPersistence`) — pas de `create()` / `close()` Domain.

## Mapping erreurs SQL → Domain

| Situation | SQLSTATE / signal | Exception / code |
|---|---|---|
| 2ᵉ session open même holder | **23505** + nom contrainte `uq_cash_session_one_open_per_holder` uniquement (pas tout 23505) | `CashSessionAlreadyOpenException` → `cash_session.already_open` |
| Close introuvable ou déjà fermée | RAISE `Session % introuvable ou déjà fermée` | `CashSessionNotFoundOrAlreadyClosedException` → `cash_session.not_found_or_already_closed` (une seule exception, miroir SQL) |
| Compte party manquant (holder / office / opened_by / closed_by) | Pré-contrôle DBAL `party_account` | `CashSessionReferencedAccountNotFoundException` — **une classe, quatre** `errorCode()` |

## Traductions (errors fr/en/ar)

- `cash_session.already_open`
- `cash_session.not_found_or_already_closed`
- `cash_session.holder_account_not_found`
- `cash_session.office_account_not_found`
- `cash_session.opened_by_not_found`
- `cash_session.closed_by_not_found`

## Tests

Integration PostgreSQL : open + hydrate, holder manquant, double-open,
close + reopen, close missing/already closed, closed_by manquant,
`findById` null.

## Qualité

- phpstan : OK
- deptrac : 0 violations
- phpunit : 384 tests, 2591 assertions (2 notices préexistants)
- phpcpd : 5 clones acceptés (todo) — aucun nouveau clone Cash session

Note : `validated_at` / `validated_by` existent en SQL mais ne sont **pas**
exposés sur l'entité Domain cette vague.
