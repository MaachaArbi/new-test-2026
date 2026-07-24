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

Tentative de suppression de la colonne orpheline `is_vat_subject`
(`party_account_organization_identity`) — **ARRÊTÉE** : 85/87 lignes portent
`true`, ce n'est pas une colonne inerte. Balayage plus large exécuté à cette
occasion : **écart systémique runtime ↔ `reference/schemas/` sur Party et
Core** (Party importé par SQL direct le 21/07, `reference/` a évolué depuis
sans migration de rattrapage). Détail complet et arbitrage requis :
`docs/journal/2026-07-24-alignement-is-vat-subject.md`.

## ⚠️ Écart runtime ↔ reference/ (Party + Core) — arbitrage pilote DB requis

- Party : 9 tables de `reference/schemas/` absentes du runtime
  (`party_account_tax_exemption`, `party_account_credit_limit`,
  `party_account_commercial_policy`, `party_account_manager_assignment`,
  `party_account_franchise` + 4 référentiels associés) ; colonnes manquantes
  sur `party_account` (`display_currency_code`, `billing_currency_code`) et
  `party_account_organization_identity` (`accounting_account_code`,
  `third_party_account_code`) ; colonnes orphelines en runtime
  (`is_vat_subject` — **85 lignes à true, NE PAS supprimer sans arbitrage** ;
  `party_account_office_relation.is_approved/approved_at/approved_by` — vides,
  sans risque) ; seed `party_account_group_type` désynchronisé
  (`commercial`/`zone` en base vs `contracting`/`pricing`/`collection`/
  `reporting` en référence).
- Core : seule `core_credential` existe en runtime (1/7 tables logiques
  attendues) ; Auth avancée (`core_session`, `core_auth_attempt`,
  `core_mfa_totp`, `core_mfa_recovery_code`, `core_credential_provider`,
  `party_role_security_policy`) et 2 colonnes de verrouillage sur
  `core_credential` n'ont jamais été migrées en runtime.

## Prochaine action

Arbitrage pilote DB sur l'écart ci-dessus (priorité : décider du sort de
`is_vat_subject`, données réelles en jeu) — **ou** Cash Management
(décaissement / transferts / conversions, comptage/clôture, validation
caissier central).
