# STATUS — OsTravel

**Symfony** 7.4.14 · **PHP** 8.4.23 · **Postgres** 16  
**phpcpd** 6.0.3 · **reference/** présent  
**Qualité** : phpstan OK · deptrac 0 · phpunit 384/2591 · phpcpd clones acceptés (todo)

## Modules

| Module | État |
|---|---|
| Party | CRUD HTTP + assignations ; lectures DBAL ; UnitOfWork |
| Shared | Domain VO + NumericDecimal + UnitOfWork + PHPStan flush |
| Core | Credential + JWT ; UnitOfWork |
| Booking | HTTP complet sur tout le pan financier historisé (charges, settlements, payer-splits). Reste : payment (différé), B15-B18/C3 ADR-003 (différé). |
| Règlements | HTTP complet sur instrument/transition/crédit/matching/solde. Reste : orchestration auto-matching. |
| Cash Management | Référentiel routing fait. Pivot `cash_session` open/close fait (DBAL → fonctions SQL). Reste : movements/balances, validate/reverse/allocate, banque, dépôts, HTTP. |

## Dernière action

Pivot Cash `cash_session` (open/close) —
`docs/journal/2026-07-23-cash-session-open-close.md`

## Prochaine action

Cash Management — `cash_movement` / balances, **ou**
fonctions validate/reverse/allocate.
