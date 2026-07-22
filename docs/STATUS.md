# STATUS — OsTravel

**Symfony** 7.4.14 · **PHP** 8.4.23 · **Postgres** 16  
**phpcpd** 6.0.3 · **reference/** présent  
**Qualité** : phpstan OK · deptrac 0 · phpunit 365/2404 · phpcpd clones acceptés (todo)

## Modules

| Module | État |
|---|---|
| Party | CRUD HTTP + assignations ; lectures DBAL ; UnitOfWork |
| Shared | Domain VO + NumericDecimal + UnitOfWork + PHPStan flush |
| Core | Credential + JWT ; UnitOfWork |
| Booking | HTTP complet sur tout le pan financier historisé (charges, settlements, payer-splits). Reste : payment (différé), B15-B18/C3 ADR-003 (différé). |
| Règlements | HTTP complet sur instrument/transition/crédit/matching/solde. Reste : orchestration auto-matching. |

## Dernière action

Correction HTTP Règlements (publicId matching, lastEntryId retiré, not_active=409) —  
`docs/journal/2026-07-22-reglements-http.md`

## Prochaine action

Orchestration auto-matching.
