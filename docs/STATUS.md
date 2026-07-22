# STATUS — OsTravel

**Symfony** 7.4.14 · **PHP** 8.4.23 · **Postgres** 16  
**phpcpd** 6.0.3 · **reference/** présent  
**Qualité** : phpstan OK · deptrac 0 · phpunit 376/2482 · phpcpd clones acceptés (todo)

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

Référentiel Cash Management routing (`cash_routing_type` +
`cash_payment_method_routing`) —
`docs/journal/2026-07-22-cash-payment-method-routing.md`

## Prochaine action

Cash Management — pivot `cash_session` / `cash_movement`, **ou**
orchestration auto-matching Règlements.
