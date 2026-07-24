## Reprise à froid

§64 — renommage FR→EN de 41 codes de référentiels (périmètre B, données).
Suite de §39 (identifiants). Mapping validé code par code avec le pilote DB.
Migration `Version20260724140000` + `reference/` + backend. Abréviations
`settlement_payment_method` hors périmètre. Vérifications + tests
fonctionnels (échec silencieux possible sur `WHERE code = '...'`).

## Origine

```
TASK — §64 : codes de référentiels FR → EN (41 codes)

CONTEXTE ET ORIGINE
Périmètre B du renommage anglais, décidé par l'utilisateur le 24/07/2026 et instruit code par
code avec le chat pilote DB architect. §39 (périmètre A, identifiants) est clos et validé ;
celui-ci porte sur les VALEURS de codes dans les référentiels.

⚠️ NATURE DU RISQUE
Contrairement à §39, ce sont des DONNÉES, pas des identifiants. Une fonction SQL qui fait
`WHERE code = 'depot'` après renommage NE PLANTE PAS : elle ne trouve aucune ligne et
retourne NULL. L'échec est silencieux. Seuls le recensement exhaustif et les tests
fonctionnels valident ce travail.

HORS PÉRIMÈTRE (décision utilisateur) : abréviations settlement_payment_method
('AD','CB','C','E','V','VE','LC','PC','RC','PE').

MAPPING — 41 codes
GROUPE 1 (PK=id, 28) : settlement_entry_type (6), cash_movement_type (15),
  cash_bank_transaction_type (7) — bank_deposit PAS deposit (homonymie).
GROUPE 2 (PK=code, 11) : party_role (2), party_function (3), cash_routing_type (4),
  booking_charge_type (2) — séquence INSERT/UPDATE enfants/DELETE.
GROUPE 3 (CHECK only, 2+) : agence_principale→main_agency, distributeur→distributor,
  client→customer dans CHECK pricing.

PHASES 0–6 : DROP CHECK → UPDATE G1 → séquence G2 → réécrire fonctions →
recréer CHECK → reference/ + backend (pas migrations historiques) → vérifs +
tests fonctionnels + qualité → journal + commit + PUSH.
```

## Décisions prises

- 41 codes renommés FR→EN, mapping validé code par code (utilisateur, 24/07)
- `bank_deposit` plutôt que `deposit` pour éviter l'homonymie avec settlement_entry_type (architecte DB)
- Abréviations settlement_payment_method hors périmètre (utilisateur)

---

# Journal — 2026-07-24 — §64 codes référentiels FR→EN

## Artefacts

- `migrations/Version20260724140000.php` (enregistrée dans `doctrine_migration_versions`)
- `reference/schemas/` : seeds, CHECK, corps de fonctions, VARCHAR routing
- Backend : `InstrumentPartyRole`, `BeneficiaryRole`, handlers Settlement/Cash, tests

## Notes techniques

- `cash_bank_transaction_type` **absente** en runtime → 7 codes banque EN seulement
  dans `reference/schemas/schema-cash-management-v1.sql`.
- `external_transmission` (21) > `VARCHAR(20)` → élargissement `VARCHAR(40)` de
  `cash_routing_type.code` et `cash_payment_method_routing.routing_type_code`.
- Trigger append-only `trg_settlement_ledger_no_mutation` désactivé le temps du
  rewrite `settlement_ledger_entry.party_role`, puis réactivé.

## Vérification 1 — codes en base (brut)

```text
           t           |           code            
-----------------------+---------------------------
 booking_charge_type   | accommodation
 booking_charge_type   | city_tax
 booking_charge_type   | commission
 booking_charge_type   | discount
 booking_charge_type   | dropoff_fee
 booking_charge_type   | fare
 booking_charge_type   | file_fee
 booking_charge_type   | fiscal_stamp
 booking_charge_type   | margin_distributor
 booking_charge_type   | margin_main_agency
 booking_charge_type   | meal
 booking_charge_type   | other
 booking_charge_type   | passenger_insurance
 booking_charge_type   | pickup_fee
 booking_charge_type   | refund
 booking_charge_type   | rental_base
 booking_charge_type   | room_rate
 booking_charge_type   | service_fee
 booking_charge_type   | supplement
 booking_charge_type   | transfer_fee
 booking_charge_type   | vehicle_insurance
 booking_charge_type   | vehicle_transport
 booking_charge_type   | withholding_tax
 cash_movement_type    | bank_deposit_out
 cash_movement_type    | closing_variance
 cash_movement_type    | conversion_in
 cash_movement_type    | conversion_out
 cash_movement_type    | external_transmission_out
 cash_movement_type    | free_credit
 cash_movement_type    | free_debit
 cash_movement_type    | generic_correction
 cash_movement_type    | instrument_receipt
 cash_movement_type    | returned_instrument_out
 cash_movement_type    | session_validation_in
 cash_movement_type    | session_validation_out
 cash_movement_type    | supplier_disbursement
 cash_movement_type    | transfer_in
 cash_movement_type    | transfer_out
 cash_routing_type     | cash_session
 cash_routing_type     | direct_bank
 cash_routing_type     | external_transmission
 cash_routing_type     | none
 party_function        | booking_agent
 party_function        | contracting
 party_function        | finance
 party_function        | manager
 party_function        | member
 party_role            | channel
 party_role            | customer
 party_role            | internal_user
 party_role            | supplier
 party_role            | system
 settlement_entry_type | balance_transfer
 settlement_entry_type | customer_obligation
 settlement_entry_type | customer_payment
 settlement_entry_type | customer_refund
 settlement_entry_type | deposit
 settlement_entry_type | reversal
 settlement_entry_type | supplier_obligation
 settlement_entry_type | supplier_payment
(60 rows)
```

`cash_bank_transaction_type` : relation absente en runtime.

## Vérification 2 — fonctions FR résiduelles

```text
 proname 
---------
(0 rows)

    k     | v 
----------+---
 V2_count | 0
(1 row)
```

## Vérification 3 — CHECK FR résiduels

```text
 conrelid | conname | pg_get_constraintdef 
----------+---------+----------------------
(0 rows)
```

## Vérification 4 — orphelins groupe 2

```text
            child             | orphans 
------------------------------+---------
 party_account_role           |       0
 party_role_translation       |       0
 party_account_function       |       0
 party_function_translation   |       0
 cash_payment_method_routing  |       0
 booking_charge               |       0
```

(`booking_charge_type_translation` absente en runtime.)

## Vérification 5 — chaîne reference/ 16/16

```text
=== STEP 1/16 … 16/16 === OK chaque étape
=== TABLE COUNT ===
293
```

## Vérification 6 — tests fonctionnels

```text
settlement_post_transfer(..., 'customer', ..., 'customer', ...) => transfer_id=23
ledger legs: customer/-1000 et customer/+1000
settlement_entry_type WHERE code='balance_transfer' => id=8 (NON NULL)
cash_movement_type WHERE code='instrument_receipt' => id=1 (NON NULL)
cash_receive_instrument(session=1, instrument=1, ...) => movement_id=31
cash_movement type_code=instrument_receipt amount=250750
```

## Vérification 7 — qualité

```text
phpstan : OK
php-cs-fixer (fichiers touchés) : OK
deptrac : Violations 0
phpunit : OK — Tests: 397, Assertions: 2681, PHPUnit Notices: 2
phpcpd : échec préexistant connu (non bloqueur)
```

## Conclusion

**Conforme** sur les 7 vérifications (hors table banque absente).  
**Écarts / notes :**
1. `cash_bank_transaction_type` absente en runtime — codes EN seulement en reference/.
2. Élargissement VARCHAR(20)→(40) routing rendu nécessaire par `external_transmission`.
3. phpcpd préexistant.

---

## Clôture §64

Oubli d’inventaire du pilote DB (pas de Cursor) : `cash_deposit_type` —
renommage unique `especes` → `cash`. `cheque` et `lcn` inchangés (décision
utilisateur 24/07). Règle migrations atomiques documentée.

### PARTIE 1 — `especes` → `cash`

- `reference/schemas/schema-cash-management-v1.sql` : seed mis à jour.
- Runtime : `cash_deposit_type` **absente** (comme `cash_bank_transaction_type`)
  → **pas** de migration runtime.
- Aucune occurrence de `'especes'` dans `reference/`, `src/`, `tests/` après.
- Aucun corps de fonction SQL ni PHP ne comparait `'especes'` en dur.

### PARTIE 2 — trigger append-only (brut)

```text
              tgname               | etat  | tgenabled 
-----------------------------------+-------+-----------
 trg_settlement_ledger_no_mutation | ACTIF | O
(1 row)

 relname | tgname | tgenabled 
---------+--------+-----------
(0 rows)
```

Invariant confirmé ; aucune réactivation nécessaire. Aucun autre trigger désactivé.

### PARTIE 3 — règle migrations atomiques

Créé : `docs/decisions/2026-07-24-migrations-sql-atomiques.md`
(architecte DB, constat vérification e20b21d).  
`Version20260724140000` **non** réécrite (fait daté).

### Vérifications clôture

1. Trigger : ACTIF (ci-dessus).
2. Chaîne verify `cash_deposit_type` :
```text
  code  
--------
 cash
 cheque
 lcn
(3 rows)
```
3. `especes` : 0 occurrence reference/src/tests.
4. Chaîne 16/16, 293 tables (`ostravel_chain_verify`, `ON_ERROR_STOP=1`).
5. Qualité : phpstan OK ; deptrac Violations 0 ; phpunit OK.
