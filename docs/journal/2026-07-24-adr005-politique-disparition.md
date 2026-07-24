## Reprise à froid

§48-1 — réécriture de l'ADR-005 sur la réalité construite (quatre régimes
de disparition). Aucun changement de schéma. Repository générique débloqué.
§48 clos. Push inclus.

## Origine

```
TASK — §48-1 : réécrire l'ADR-005 (politique de suppression) sur la réalité construite

ADR-005 date d'il y a six mois et décrit une politique qui n'a jamais été appliquée.
Recensement exhaustif : l'ADR se trompe DANS LES DEUX SENS.
Piège actif : ajouter deleted_at sur settlement_ledger_entry casserait le grand livre.
Bloquant backend : Repository générique (EN ATTENTE).

AUCUN CHANGEMENT DE SCHÉMA. Contenu validé utilisateur 24/07 :
  Titre « ADR-005 : Politique de disparition — quatre régimes »
  Quatre régimes + tableau de décision + pourquoi l'ancienne version était fausse.
Portée : meta ADR, backend-cadrage, module-index, project_overview, en-têtes SQL,
sujets-reportes §48 clos.

Vérifs + journal + commit + PUSH.
```

## Décisions prises

- Quatre régimes plutôt qu'un choix binaire soft/hard delete (utilisateur, 24/07,
  sur analyse du chat pilote DB)
- L'ADR doit être actionnable pour le Repository générique — tableau de décision
  inclus (architecte DB)

---

# Journal — 2026-07-24 — ADR-005 politique de disparition (§48-1)

## Fichiers

- `reference/meta/01-architecture_decisions.md` — ADR-005 réécrit
- `reference/backend-cadrage/01-backend-architecture-decisions.md` — EN ATTENTE → résolu, débloqué
- `reference/backend-cadrage/02-backend-module-index.md`
- `reference/meta/00-project_overview.md`
- `reference/meta/sujets-reportes.md` — §48 clos
- En-têtes commentaires uniquement : `schema-party-account-v1.sql`,
  `schema-booking-v1.sql`, `schema-pricing-v1.sql`

## Vérification catalogue (brut)

### Q1 — Régime 1 (`deleted_at`)

```text
SELECT table_name FROM information_schema.columns
WHERE table_schema='public' AND column_name='deleted_at' ORDER BY 1;

       table_name       
------------------------
 booking_folder
 core_credential
 party_account
 party_account_address
 party_account_document
(5 rows)
```

### Q2 — Régime 3 (`is_active` / `is_disabled`)

```text
SELECT DISTINCT table_name FROM information_schema.columns
WHERE table_schema='public' AND column_name IN ('is_active','is_disabled')
ORDER BY 1;

         table_name          
-----------------------------
 booking_service_type
 cash_movement_type
 cash_payment_method_routing
 core_role
 document_template
 document_trigger_rule
 party_account
 pricing_rule
 product_vehicle_unit
 provider_connection
 ref_currency
 ref_language
 sales_point
 settlement_payment_method
(14 rows)
```

### Q3 — Régime 2 (triggers guard / no_mutation)

```text
SELECT c.relname AS table_name, t.tgname
FROM pg_trigger t
JOIN pg_class c ON c.oid = t.tgrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname='public' AND NOT t.tgisinternal
  AND (t.tgname ILIKE '%no_mutation%' OR t.tgname ILIKE '%guard%')
ORDER BY 1, 2;

       table_name        |              tgname               
-------------------------+-----------------------------------
 cash_bank_transaction   | trg_cash_bank_transaction_guard
 cash_movement           | trg_cash_movement_guard
 settlement_ledger_entry | trg_settlement_ledger_no_mutation
(3 rows)
```

## Chaîne / schéma

```text
git diff --stat reference/schemas/ → 3 fichiers, commentaires d'en-tête seulement
Chaîne 16/16, 299 tables (inchangé)
```

## Qualité

```text
phpstan OK · deptrac 0 violations · phpunit 397 OK (2 notices préexistants)
```
