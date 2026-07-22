# STATUS — OsTravel

**Symfony** 7.4.14 · **PHP** 8.4.23 · **Postgres** 16  
**phpcpd** 6.0.3 · **reference/** présent  
**Qualité** : phpstan OK · deptrac 0 · phpunit 377/2531 · phpcpd clones acceptés (todo)

## Modules

| Module | État |
|---|---|
| Party | CRUD HTTP + assignations ; lectures DBAL ; UnitOfWork |
| Shared | Domain VO + NumericDecimal + UnitOfWork + PHPStan flush |
| Core | Credential + JWT ; UnitOfWork |
| Booking | HTTP complet sur tout le pan financier historisé (charges, settlements, payer-splits). Reste : payment (différé), B15-B18/C3 ADR-003 (différé). |
| Règlements | HTTP complet sur instrument/transition/crédit/matching/solde. Reste : orchestration auto-matching. |
| Cash Management | Référentiel routing fait. Reste : cash_session/cash_movement (pivot), fonctions PL/pgSQL à appeler (validate/reverse/allocate...), banque, dépôts, rapprochement, HTTP. |

## Dernière action

Alignement migration Cash routing sur schéma officiel —
`docs/journal/2026-07-22-cash-payment-method-routing-schema-fix.md`

## Prochaine action

Cash Management — pivot `cash_session` / `cash_movement`, **ou**
orchestration auto-matching Règlements.
