# STATUS — OsTravel

**Symfony** 7.4.14 · **PHP** 8.4.23 · **Postgres** 16  
**phpcpd** 6.0.3 · **reference/** présent  
**Qualité** : phpstan OK · deptrac 0 · phpunit 397/2680 · phpcpd clones acceptés (todo)

## Modules

| Module | État |
|---|---|
| Party | CRUD HTTP + assignations ; lectures DBAL ; UnitOfWork |
| Shared | Domain VO + NumericDecimal + UnitOfWork + PHPStan flush |
| Core | Credential + JWT ; UnitOfWork |
| Booking | HTTP complet sur tout le pan financier historisé (charges, settlements, payer-splits). Reste : payment (différé), B15-B18/C3 ADR-003 (différé). |
| Settlement | HTTP complet sur instrument/transition/crédit/matching/solde. Préfixe API `/api/v1/settlements/...` depuis le 24/07 (ex-`/reglements/...`, §39). Orchestration auto-matching **différée** (reprise chantier frontend). |
| Cash Management | cash_movement_type + cash_movement migrés, encaissement d'instrument fait, 5 validations métier. Reste : décaissement, transferts, conversions, comptage/clôture, validation caissier central, banque, dépôts, rapprochement, HTTP. |

## Dernière action

Encaissement instrument caisse —
`docs/journal/2026-07-23-cash-receive-instrument.md`

## Prochaine action

Cash Management — décaissement / transferts / conversions, **ou**
comptage/clôture / validation caissier central.
