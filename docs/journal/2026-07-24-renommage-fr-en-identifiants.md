## Reprise à froid

Renommage atomique FR→EN des identifiants BDD §39 périmètre A :
`reglement_*`→`settlement_*`, `pointvente`→`sales_point`,
`pointvente_paiement`→`sales_point_payment`. Couvre reference/, migration
Doctrine (base réelle), backend `Reglements`→`Settlement`. Valeurs de codes
préservées (§64). Vérifications Phase 4 ci-dessous.

## Origine

```
TASK — Renommage FR → EN des identifiants BDD (§39, périmètre A) : reference/ + base + backend

CONTEXTE ET ORIGINE
Décision utilisateur du 24/07/2026, instruite par le chat pilote DB architect : les deux
derniers modules à préfixe français passent à l'anglais. Toutes les autres sessions sont en
instance — l'opération se fait donc en une passe atomique. Le renommage a été intégralement
conçu et validé en sandbox PostgreSQL 16 par le pilote DB (chaîne 16/16, 293 tables, base
migrée par ALTER structurellement IDENTIQUE à une base reconstruite depuis les schémas :
1969 colonnes, 712 index, 1013 contraintes, 106 fonctions, zéro différence).

RENOMMAGE À APPLIQUER
  reglement_*  ->  settlement_*      (7 tables, 3 fonctions, index/contraintes associés)
  pointvente   ->  sales_point       (1 table)
  booking.pointvente_id          -> booking.sales_point_id
  booking.pointvente_paiement_id -> booking.sales_point_payment_id
      (l'ancien nom était doublement français — renommé entièrement en anglais,
       PAS en sales_point_paiement_id qui serait du demi-anglais)

⚠️ HORS PÉRIMÈTRE — NE PAS TOUCHER (périmètre B, §64)
Les VALEURS de données restent françaises. Ne jamais les renommer :
  'reglement_client', 'reglement_fournisseur'  (dans settlement_entry_type.code)
  'reglement_direct'                            (dans cash_bank_transaction_type.code)
Ce sont des données, pas des identifiants. Les renommer casserait les comparaisons de
chaînes côté backend — décision séparée, tracée en §64.
Les COMMENTAIRES français restent français (convention du projet : identifiants anglais,
commentaires français). Ne pas traduire la prose.

═══════════════════════════════════════════════════════════════════
PHASE 1 — reference/ (documentation)
═══════════════════════════════════════════════════════════════════
Exception explicitement autorisée par l'utilisateur à la règle « reference/ en lecture
seule » : cette transformation vient de l'utilisateur via le pilote DB, ce que le README
prévoit.

Transformation à appliquer sur TOUS les .sql / .diff / .md de reference/, DANS CET ORDRE :
  1. Protéger d'abord les 3 valeurs de données ci-dessus (placeholder temporaire)
  2. 'pointvente_paiement'  ->  'sales_point_payment'
  3. 'pointvente'           ->  'sales_point'     ⚠️ SANS frontière de mot (\b) :
     un regex \bpointvente\b NE détecte PAS idx_booking_pointvente, car l'underscore
     est un caractère de mot. C'est le piège n°1, il a été rencontré réellement.
  4. 'reglement_'           ->  'settlement_'
  5. Restaurer les valeurs protégées

Renommages de fichiers (git mv, pour préserver l'historique) :
  reference/schemas/schema-reglements-v1.sql   -> schema-settlement-v1.sql
  reference/schemas/schema-pointvente-v1.sql   -> schema-sales-point-v1.sql
  reference/conceptual-models/modele-conceptuel-reglements.md -> modele-conceptuel-settlement.md
  reference/conceptual-models/modele-conceptuel-pointvente.md -> modele-conceptuel-sales-point.md
Les diffs historiques gardent leur nom d'origine (artefacts datés) : reglements-currency_code-fix.diff
reste nommé ainsi, seul son contenu est transformé.

Mettre aussi à jour les références à ces noms de fichiers À L'INTÉRIEUR des documents.

═══════════════════════════════════════════════════════════════════
PHASE 2 — Migration Doctrine (base réelle)
═══════════════════════════════════════════════════════════════════
La base contient déjà les tables reglement_*/pointvente AVEC des données. Il faut donc des
ALTER, pas un rebuild.

⚠️ PIÈGE n°2 (rencontré réellement) : ALTER TABLE ... RENAME ne renomme NI les index NI les
contraintes. Un renommage à moitié fait est pire que pas de renommage.
⚠️ PIÈGE n°3 (rencontré réellement) : `booking` est PARTITIONNÉE.
   - renommer une COLONNE sur la table parente se propage automatiquement aux partitions
     (donc ne PAS générer d'ALTER pour les partitions : elles échoueraient sur une colonne
     déjà renommée) ;
   - MAIS les INDEX et CONTRAINTES portés par les partitions ne se propagent PAS et doivent
     être renommés explicitement (booking_y2026m07_pointvente_id_idx,
     booking_pointvente_id_fkey répliquée sur chaque partition...).

Génère la migration depuis le CATALOGUE PostgreSQL (pas à la main — 100+ objets), dans cet
ordre, en une seule transaction :
  1. Tables       : pg_class relkind IN ('r','p'), EXCLURE les partitions filles
                    (NOT EXISTS dans pg_inherits sur inhrelid)
  2. Colonnes     : pg_attribute, tables parentes uniquement (même exclusion)
  3. Contraintes  : ALTER TABLE ... RENAME CONSTRAINT, tables parentes
  4. Index        : ALTER INDEX ... RENAME TO, hors index portés par une contrainte
                    (déjà traités en 3), tables parentes
  5. Fonctions    : ALTER FUNCTION ...(signature) RENAME TO
  6. Objets des PARTITIONS : index + contraintes restants (pas de filtre d'exclusion ici)

Ordre de nommage à utiliser dans les remplacements : pointvente_paiement -> sales_point_payment
AVANT pointvente -> sales_point, sinon on obtient sales_point_paiement.

═══════════════════════════════════════════════════════════════════
PHASE 3 — Backend (src/, tests/, config/)
═══════════════════════════════════════════════════════════════════
  Namespace  : App\Modules\Reglements  ->  App\Modules\Settlement
               (singulier, cohérent avec Booking / CashManagement / Core / Party)
  Répertoire : src/Modules/Reglements/ -> src/Modules/Settlement/   (git mv)
  Classes    : préfixe Reglement -> Settlement
               ex. ReglementLedgerEntryRepositoryInterface -> SettlementLedgerEntryRepositoryInterface
               ReglementInstrumentStatus -> SettlementInstrumentStatus, etc.
               (~20 classes d'exception, repositories, VO)
  Mappings Doctrine XML : noms de tables ET de colonnes
  Appels de fonctions SQL : reglement_post_transfer -> settlement_post_transfer
  config/, tests/ : mêmes règles
  Ne PAS renommer les valeurs de codes (voir hors périmètre ci-dessus).

═══════════════════════════════════════════════════════════════════
PHASE 4 — VÉRIFICATION (obligatoire, ne pas clôturer sans)
═══════════════════════════════════════════════════════════════════
En base, après migration — TOUS doivent retourner 0 :
  SELECT count(*) FROM information_schema.tables  WHERE table_schema='public'
    AND (table_name LIKE '%reglement%' OR table_name LIKE '%pointvente%');
  SELECT count(*) FROM information_schema.columns WHERE table_schema='public'
    AND (column_name LIKE '%reglement%' OR column_name LIKE '%pointvente%');
  SELECT count(*) FROM pg_indexes WHERE schemaname='public'
    AND (indexname LIKE '%reglement%' OR indexname LIKE '%pointvente%');
  SELECT count(*) FROM pg_constraint
    WHERE conname LIKE '%reglement%' OR conname LIKE '%pointvente%';
  SELECT count(*) FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
    WHERE n.nspname='public' AND p.proname LIKE 'reglement%';

Valeurs de données PRÉSERVÉES — doit retourner 3 :
  SELECT (SELECT count(*) FROM settlement_entry_type WHERE code LIKE 'reglement%')
       + (SELECT count(*) FROM cash_bank_transaction_type WHERE code LIKE 'reglement%');

Test fonctionnel réel (pas seulement structurel) :
  SELECT settlement_post_transfer(<compte_A>,'client',<compte_B>,'client','TND',
         25000, CURRENT_DATE, 'test renommage', NULL);
  puis vérifier : 1 ligne dans settlement_transfer, 2 jambes de signe opposé dans
  settlement_ledger_entry (même transfer_id), soldes corrects dans settlement_balance.

Dans le repo : grep -ri 'reglement\|pointvente' src/ tests/ config/ reference/
  -> ne doit plus rien retourner SAUF les 3 valeurs de données et la prose française
     des commentaires.

Qualité : phpstan, php-cs-fixer, deptrac, phpcpd, phpunit — suite complète verte.

═══════════════════════════════════════════════════════════════════
JOURNAL + COMMIT
═══════════════════════════════════════════════════════════════════
docs/journal/2026-07-24-renommage-fr-en-identifiants.md, conforme à
docs/decisions/2026-07-23-journal-convention-origine.md (3 blocs d'en-tête) :
  1. Reprise à froid
  2. Origine — ce prompt collé VERBATIM
  3. Décisions prises :
     - "Passage à l'anglais des identifiants reglement_/pointvente (utilisateur, 24/07)"
     - "Périmètre limité aux identifiants ; valeurs de codes exclues et tracées en §64
        (architecte DB)"
     - "sales_point_payment_id plutôt que sales_point_paiement_id, pour éviter le
        demi-anglais (architecte DB)"
Corps : sortie brute des vérifications de la Phase 4.

Commit unique (base + code + reference/ doivent rester cohérents entre eux — ne PAS
committer la migration sans le refactor backend, sinon l'application casse) :
  refactor(naming): reglement_ -> settlement_, pointvente -> sales_point (§39 périmètre A)

À RENDRE : sortie brute des vérifications Phase 4 + résultat de la suite qualité + tout
écart constaté. Si un écart apparaît, le remonter SANS le corriger unilatéralement —
arbitrage avec le pilote DB architect.
```

## Décisions prises

- Passage à l'anglais des identifiants reglement_/pointvente (utilisateur, 24/07)
- Périmètre limité aux identifiants ; valeurs de codes exclues et tracées en §64 (architecte DB)
- sales_point_payment_id plutôt que sales_point_paiement_id, pour éviter le demi-anglais (architecte DB)

---

# Journal — 2026-07-24 — renommage FR→EN identifiants (§39 A)

## Artefacts

- Migration : `migrations/Version20260724100000.php` (générée depuis `pg_catalog` + rewrite corps fonctions)
- Module : `src/Modules/Reglements` → `src/Modules/Settlement`
- Note runtime : table `pointvente` / `sales_point` absente de la base réelle (seules les colonnes `booking.pointvente_*` existaient) — rien à renommer côté table `sales_point`.
- Complément migration : rewrite de `cash_receive_instrument()` (référençait `reglement_instrument` dans le corps, hors détection par `proname`).

## Phase 4 — sorties brutes

### Structure (attendu : tous 0)

```text
=== 1. tables (attendu 0) ===
 count 
-------
     0
(1 row)

=== 2. columns (attendu 0) ===
 count 
-------
     0
(1 row)

=== 3. indexes (attendu 0) ===
 count 
-------
     0
(1 row)

=== 4. constraints (attendu 0) ===
 count 
-------
     0
(1 row)

=== 5. functions reglement% (attendu 0) ===
 count 
-------
     0
(1 row)
```

### Valeurs de données (attendu : 3)

```text
 n_settlement_entry_type | codes_exact 
-------------------------+-------------
                       2 |           2
(1 row)

         code          
-----------------------
 reglement_client
 reglement_fournisseur
(2 rows)

 cash_bank_transaction_type_exists 
-----------------------------------
 (NULL — relation absente)
```

**Écart :** la requête demandée échoue / ne peut pas totaliser 3 car
`cash_bank_transaction_type` **n'existe pas** dans la base runtime (module cash banque
non déployé). Les 2 codes `reglement_client` / `reglement_fournisseur` sont bien
préservés. `reglement_direct` reste correct dans `reference/` uniquement.

### Test fonctionnel `settlement_post_transfer`

```text
SELECT settlement_post_transfer(13195,'client',13196,'client','TND',
         25000, CURRENT_DATE, 'test renommage', NULL);
-- => transfer_id = 19

 id | source_account_id | target_account_id | amount_minor |     reason     
----+-------------------+-------------------+--------------+----------------
 19 |             13195 |             13196 |        25000 | test renommage
(1 row)

 id  | party_account_id | amount_minor | transfer_id 
-----+------------------+--------------+-------------
 521 |            13195 |       -25000 |          19
 522 |            13196 |        25000 |          19
(2 rows)

 party_account_id | balance_minor 
------------------+---------------
            13195 |         35000   -- était 60000
            13196 |         65000   -- était 40000
(2 rows)
```

### grep repo (`src/ tests/ config/ reference/`)

Restent uniquement : valeurs de codes (`reglement_client` / `reglement_fournisseur` /
`reglement_direct`), noms legacy `ost_com_reglement*`, artefact daté
`reglements-currency_code-fix.diff`, prose française (`Règlements` / `règlement`).

### Suite qualité

```text
phpstan (memory-limit=512M) : OK — No errors
php-cs-fixer (Settlement + Version20260724100000) : OK — 0 files to fix
deptrac : Violations 0
phpunit : OK — Tests: 397, Assertions: 2681, PHPUnit Notices: 2
phpcpd : ÉCART — exit 1, 5 clones / 130 lignes (préexistants : SettlementHttpSupport↔BookingHttpSupport, entités Settlement, BookingPayerSplit↔BookingSettlement)
```

## Conclusion

**Conforme** sur structure (0 leftover FR), test fonctionnel transfer, grep hors valeurs/prose,
phpstan / cs (fichiers touchés) / deptrac / phpunit.

**Écarts remontés (sans correction unilatérale) :**
1. Compteur data values = 2 (pas 3) — table `cash_bank_transaction_type` absente en runtime.
2. `phpcpd` non vert — clones préexistants, non introduits par ce renommage.
