## Reprise à froid

Application des 7 décisions de l'audit des valeurs par défaut (pilote DB,
24/07/2026) : 5 retraits de DEFAULT, `is_delegable` → false, `mfa_issuer_name`
nullable + triggers 2FA. reference/ + migration Doctrine pour ce qui existe
déjà en base. Pas de modification des données (sauf UPDATE ciblé MyGo, N/A ici).

## Origine

```
TASK — Valeurs par défaut : appliquer les 7 décisions de l'audit (pilote DB, 24/07/2026)

CONTEXTE ET ORIGINE
Audit complet des 597 valeurs par défaut de la base, mené par le chat pilote DB architect.
571 sont techniques (horodatages, UUID, booléens is_active, numériques 0/1) — hors sujet.
22 portaient une décision métier, revues une par une avec l'utilisateur. 15 sont conservées
(états de naissance de cycle de vie, discriminants Pricing, politique de sécurité).
7 changements à appliquer, ci-dessous.

Principe qui a guidé les décisions, à retenir pour la suite : un défaut est légitime quand il
énonce un fait vrai PAR CONSTRUCTION (une facture naît brouillon, une session de caisse naît
ouverte). Il est nocif quand il devine une SAISIE que l'application aurait dû fournir — car il
transforme un oubli en donnée plausible mais fausse.

═══════════════════════════════════════════════════════════════════
A. RETRAITS DE DÉFAUT (5) — la colonne reste NOT NULL, les CHECK sont inchangés
═══════════════════════════════════════════════════════════════════
1. booking.channel_code — retirer DEFAULT 'backoffice'
   Raison : un canal non renseigné devenait silencieusement "backoffice", faussant les
   statistiques de canal. Impact vérifié NUL : le Domain passe déjà channelCode en
   paramètre obligatoire du constructeur de Booking.
   ⚠️ booking est PARTITIONNÉE : appliquer sur la table PARENTE (la propagation aux
   partitions est automatique pour un DROP DEFAULT).

2. booking_payment.status — retirer DEFAULT 'pending'
   Raison : la majorité des encaissements sont en espèces au comptoir, donc 'captured'
   immédiatement — le défaut supposait un flux asynchrone minoritaire, et un oubli faisait
   apparaître une réservation comme impayée (conséquence financière).
   Impact vérifié NUL : aucun code backend sur booking_payment, table pas encore migrée en
   base. Si la table n'existe pas encore, appliquer le changement dans le schéma de
   reference/ uniquement — pas de migration nécessaire (le noter dans le journal).

3. cash_payment_method_routing.instrument_tracking_mode — retirer DEFAULT 'not_applicable'
   Raison : la contrainte chk_routing_tracking_consistency n'autorise 'not_applicable' que
   si routing_type_code = 'aucun' (cas marginal). Dans le cas courant le défaut était
   toujours faux et produisait une erreur de contrainte croisée peu lisible ; sans défaut,
   l'erreur NOT NULL dit directement ce qui manque.

4. party_account_address.address_type — retirer DEFAULT 'legal'
   Raison : 'legal' porte un vocabulaire d'entreprise, appliqué aussi aux comptes
   nature='person' pour qui l'adresse « légale » n'a pas de sens (plutôt 'domiciliation').
   C'est une saisie, pas un état initial.

5. ref_currency.minor_unit — retirer DEFAULT 2
   Raison : la devise principale du métier (TND) a 3 décimales, pas 2. Le défaut encode la
   convention du monde majoritaire. Une devise ajoutée sans préciser minor_unit stockerait
   tous ses montants avec un facteur 10 d'écart — erreur monétaire silencieuse.
   Les seeds existants (TND=3, EUR=2, USD=2, DZD=2) sont corrects et ne changent PAS.

═══════════════════════════════════════════════════════════════════
B. MODIFICATIONS (2)
═══════════════════════════════════════════════════════════════════
6. core_permission.is_delegable : DEFAULT true  ->  DEFAULT false
   Raison : is_delegable=false est un contrôle de sécurité (plafond universel empêchant un
   admin délégué de franchise d'octroyer une permission sensible). Avec DEFAULT true, un
   développeur qui oublie le flag sur une permission sensible ouvre SILENCIEUSEMENT une
   escalade de privilèges. Avec false, l'oubli bloque une action légitime — panne bruyante,
   signalée le jour même, sans risque. Principe des défauts sûrs.
   ⚠️ NE PAS modifier les lignes déjà seedées : ce changement ne concerne que le défaut
   pour les futures permissions. Vérifier et rapporter combien de lignes existent
   actuellement avec is_delegable=true, sans les toucher.

7. config_application_setting.mfa_issuer_name : NOT NULL DEFAULT 'MyGo' -> NULLABLE sans défaut
   Raison : 'MyGo' est le nom d'un CLIENT précis, codé en dur dans un schéma déployé chez
   TOUS les clients — un autre client verrait « MyGo » dans son application
   d'authentification. Le schéma ne doit coder en dur aucun nom (ni client, ni produit).
   Le client doit renseigner son propre nom avant d'activer le 2FA pour ses utilisateurs.

   Décision utilisateur explicite : la garantie doit être EN BASE (pas seulement en
   Application), via deux triggers. Ils ont été construits et testés en sandbox par le
   pilote DB — les 4 scénarios passent. À reproduire tels quels :

   -- Colonne
   ALTER TABLE config_application_setting ALTER COLUMN mfa_issuer_name DROP NOT NULL;
   ALTER TABLE config_application_setting ALTER COLUMN mfa_issuer_name DROP DEFAULT;
   UPDATE config_application_setting SET mfa_issuer_name = NULL
     WHERE id = 1 AND mfa_issuer_name = 'MyGo';   -- ne vider que si c'est encore le défaut

   -- Trigger A : interdire d'ACTIVER le 2FA sans issuer renseigné
   CREATE OR REPLACE FUNCTION core_mfa_require_issuer() RETURNS TRIGGER AS $$
   BEGIN
       IF NOT EXISTS (SELECT 1 FROM config_application_setting
                      WHERE id = 1 AND btrim(coalesce(mfa_issuer_name,'')) <> '') THEN
           RAISE EXCEPTION 'Activation 2FA refusee : config_application_setting.mfa_issuer_name doit etre renseigne au prealable (nom affiche dans l''application d''authentification de l''utilisateur).'
               USING ERRCODE = 'check_violation';
       END IF;
       RETURN NEW;
   END; $$ LANGUAGE plpgsql;

   CREATE TRIGGER trg_core_mfa_require_issuer
       BEFORE INSERT OR UPDATE OF is_enabled ON core_mfa_totp
       FOR EACH ROW WHEN (NEW.is_enabled)
       EXECUTE FUNCTION core_mfa_require_issuer();

   -- Trigger B : interdire d'EFFACER l'issuer si des 2FA sont deja actifs
   CREATE OR REPLACE FUNCTION config_protect_mfa_issuer() RETURNS TRIGGER AS $$
   BEGIN
       IF btrim(coalesce(NEW.mfa_issuer_name,'')) = ''
          AND EXISTS (SELECT 1 FROM core_mfa_totp WHERE is_enabled) THEN
           RAISE EXCEPTION 'Effacement refuse : mfa_issuer_name ne peut pas etre vide tant que des utilisateurs ont le 2FA actif.'
               USING ERRCODE = 'check_violation';
       END IF;
       RETURN NEW;
   END; $$ LANGUAGE plpgsql;

   CREATE TRIGGER trg_config_protect_mfa_issuer
       BEFORE UPDATE OF mfa_issuer_name ON config_application_setting
       FOR EACH ROW EXECUTE FUNCTION config_protect_mfa_issuer();

   Le WHEN (NEW.is_enabled) du trigger A est essentiel : il autorise la création d'une
   inscription 2FA non activée (is_enabled=false) même sans issuer, et ne bloque QUE
   l'activation.

   Côté Application (à faire, pas seulement le trigger) : valider AVANT d'atteindre la base
   pour afficher un message propre ("Renseignez le nom de votre organisation avant d'activer
   l'authentification à deux facteurs") plutôt qu'une exception SQL. Le trigger est un
   filet, pas l'expérience utilisateur. ERRCODE='check_violation' permet de l'attraper
   spécifiquement côté Doctrine si ça remonte.

═══════════════════════════════════════════════════════════════════
PORTÉE — les deux côtés doivent rester cohérents
═══════════════════════════════════════════════════════════════════
- reference/schemas/ : appliquer les 7 changements aux fichiers concernés
  (schema-booking-v1.sql, schema-cash-management-v1.sql, schema-party-account-v1.sql,
   schema-ref-common.sql, schema-permissions-config-v1.sql)
- Base réelle : une migration Doctrine pour ce qui existe déjà en base. Pour ce qui n'y est
  pas encore (booking_payment notamment), le schéma de référence suffit — le noter.
- Ne PAS modifier les données existantes, sauf le UPDATE ciblé de mfa_issuer_name ci-dessus.

═══════════════════════════════════════════════════════════════════
VÉRIFICATION (obligatoire)
═══════════════════════════════════════════════════════════════════
Après migration, en base :
  SELECT table_name, column_name, column_default, is_nullable
  FROM information_schema.columns
  WHERE table_schema='public' AND (
      (table_name LIKE 'booking%'   AND column_name IN ('channel_code','status'))
   OR (table_name='cash_payment_method_routing' AND column_name='instrument_tracking_mode')
   OR (table_name='party_account_address'       AND column_name='address_type')
   OR (table_name='ref_currency'                AND column_name='minor_unit')
   OR (table_name='core_permission'             AND column_name='is_delegable')
   OR (table_name='config_application_setting'  AND column_name='mfa_issuer_name'))
  ORDER BY table_name, column_name;
Attendu : column_default NULL pour les 5 retraits (y compris sur toutes les partitions de
booking), 'false' pour is_delegable, NULL + is_nullable='YES' pour mfa_issuer_name.

Test fonctionnel des triggers 2FA — les 4 scénarios, sortie brute :
  1. activer un 2FA avec issuer vide            -> doit ECHOUER
  2. creer un 2FA is_enabled=false, issuer vide  -> doit PASSER
  3. renseigner l'issuer puis activer            -> doit PASSER
  4. effacer l'issuer avec un 2FA actif          -> doit ECHOUER
  (nettoyer les donnees de test apres)

Qualité : phpstan, php-cs-fixer, deptrac, phpcpd, phpunit — suite complète verte.

═══════════════════════════════════════════════════════════════════
JOURNAL + COMMIT + PUSH
═══════════════════════════════════════════════════════════════════
docs/journal/2026-07-24-audit-valeurs-par-defaut.md, conforme à
docs/decisions/2026-07-23-journal-convention-origine.md (3 blocs d'en-tête).
Bloc "Décisions prises" — attribuer chacune :
  - Retrait des défauts sur channel_code, booking_payment.status, address_type,
    minor_unit (utilisateur, 24/07)
  - Retrait sur instrument_tracking_mode (architecte DB, choix délégué par l'utilisateur)
  - is_delegable DEFAULT false (utilisateur, sur recommandation architecte DB)
  - mfa_issuer_name nullable + garantie EN BASE par triggers plutôt qu'en Application
    (utilisateur, 24/07)
  - 15 défauts conservés : états de naissance de cycle de vie, discriminants Pricing,
    politique de sécurité 5/15 (utilisateur)
Corps : sortie brute des vérifications.

Commit : refactor(schema): appliquer les 7 décisions de l'audit des valeurs par défaut

⚠️ PUSH OBLIGATOIRE : la tâche n'est PAS terminée tant que le commit n'est pas sur
origin/main. Un commit local est invisible pour le pilote DB qui doit le vérifier.
Confirmer le push dans le rapport final.

À RENDRE : sortie brute des vérifications + résultat des 4 tests de triggers + suite qualité
+ confirmation du push. Tout écart : le remonter SANS le corriger unilatéralement.
```

## Décisions prises

- Retrait des défauts sur `channel_code`, `booking_payment.status`, `address_type`, `minor_unit` (utilisateur, 24/07)
- Retrait sur `instrument_tracking_mode` (architecte DB, choix délégué par l'utilisateur)
- `is_delegable` DEFAULT false (utilisateur, sur recommandation architecte DB)
- `mfa_issuer_name` nullable + garantie EN BASE par triggers plutôt qu'en Application (utilisateur, 24/07)
- 15 défauts conservés : états de naissance de cycle de vie, discriminants Pricing, politique de sécurité 5/15 (utilisateur)

---

# Journal — 2026-07-24 — audit valeurs par défaut

## Artefacts

- Migration runtime : `migrations/Version20260724120000.php`
- reference/ : `schema-booking-v1.sql`, `schema-cash-management-v1.sql`,
  `schema-party-account-v1.sql`, `schema-ref-common.sql`,
  `schema-permissions-config-v1.sql`, `diff-core-auth-avancee.sql` (triggers MFA)

## Portée runtime vs reference/

| # | Colonne | reference/ | Base réelle |
|---|---|---|---|
| 1 | `booking.channel_code` | DROP DEFAULT | Migration appliquée (parent + 4 partitions) |
| 2 | `booking_payment.status` | DROP DEFAULT | **Table absente** — reference/ seul |
| 3 | `cash_payment_method_routing.instrument_tracking_mode` | DROP DEFAULT | Déjà sans défaut en runtime ; `DROP DEFAULT` idempotent dans la migration |
| 4 | `party_account_address.address_type` | DROP DEFAULT | Migration appliquée |
| 5 | `ref_currency.minor_unit` | DROP DEFAULT | Migration appliquée ; seeds inchangés |
| 6 | `core_permission.is_delegable` | DEFAULT false | **Table absente** — reference/ seul ; lignes seedées N/A |
| 7 | `config_application_setting.mfa_issuer_name` + triggers | nullable + triggers dans `diff-core-auth-avancee.sql` | **Tables absentes** — reference/ seul ; UPDATE MyGo N/A |

**Application MFA** : aucun handler/module MFA dans `src/` — validation UX différée
jusqu'à l'implémentation Core MFA (le trigger reste le filet en base une fois le
schéma déployé).

**`is_delegable=true` en base** : table `core_permission` absente → count = N/A (0 ligne
existante à ne pas toucher).

## Vérification information_schema (sortie brute)

```text
         table_name          |       column_name        | column_default | is_nullable 
-----------------------------+--------------------------+----------------+-------------
 booking                     | channel_code             |                | NO
 booking_default             | channel_code             |                | NO
 booking_y2026m07            | channel_code             |                | NO
 booking_y2026m08            | channel_code             |                | NO
 booking_y2026m09            | channel_code             |                | NO
 cash_payment_method_routing | instrument_tracking_mode |                | NO
 party_account_address       | address_type             |                | NO
 ref_currency                | minor_unit               |                | NO
(8 rows)

 code | minor_unit 
------+------------
 DZD  |          2
 EUR  |          2
 TND  |          3
 USD  |          2
```

`core_permission` / `config_application_setting` / `booking_payment` : absents
(column_default is_delegable / mfa_issuer_name non vérifiables en runtime).

## Tests triggers 2FA (harness éphémère — tables créées puis DROP)

```text
--- 1. activer 2FA avec issuer vide -> doit ECHOUER ---
NOTICE:  TEST1 PASS: Activation 2FA refusee : config_application_setting.mfa_issuer_name doit etre renseigne au prealable (nom affiche dans l'application d'authentification de l'utilisateur).

--- 2. creer 2FA is_enabled=false, issuer vide -> doit PASSER ---
   result   | credential_id | is_enabled 
------------+---------------+------------
 TEST2 PASS |             1 | f

--- 3. renseigner issuer puis activer -> doit PASSER ---
   result   | is_enabled | mfa_issuer_name 
------------+------------+-----------------
 TEST3 PASS | t          | Acme Travel

--- 4. effacer issuer avec 2FA actif -> doit ECHOUER ---
NOTICE:  TEST4 PASS: Effacement refuse : mfa_issuer_name ne peut pas etre vide tant que des utilisateurs ont le 2FA actif.

=== Nettoyage harness ===
 cleanup 
---------
 CLEANED
```

## Suite qualité

```text
phpstan (memory-limit=512M) : OK
php-cs-fixer (Version20260724120000) : OK (fixé)
deptrac : Violations 0
phpunit : OK — Tests: 397, Assertions: 2681, PHPUnit Notices: 2
phpcpd : ÉCART préexistant — exit 1, 5 clones / 130 lignes (inchangé)
```

## Correctif placement triggers

**Régression** constatée par le pilote DB en rejouant la chaîne après `63dd92d` :
les 2 fonctions + 2 triggers 2FA étaient dans `diff-core-auth-avancee.sql` (étape 7),
alors que `trg_config_protect_mfa_issuer` porte sur `config_application_setting`
(créée à l'étape 15). La chaîne échouait :
`psql:diff-core-auth-avancee.sql:173: ERROR: relation "config_application_setting" does not exist`.

**Correctif** : bloc déplacé tel quel vers la **fin** de
`schema-permissions-config-v1.sql`, avec commentaire d'interdiction de re-rangements
futurs. À l'étape 15, `core_mfa_totp` (étape 7) et `config_application_setting`
coexistent — seul point valide de la chaîne.

Aucune migration runtime (tables toujours absentes en prod runtime).

### Rejeu chaîne 16/16 (base vierge `ostravel_chain_verify`, `ON_ERROR_STOP=1`)

```text
=== STEP 1/16: schema-ref-common.sql ===
OK step 1
=== STEP 2/16: schema-party-account-v1.sql ===
OK step 2
=== STEP 3/16: schema-log-v1.sql ===
OK step 3
=== STEP 4/16: schema-core-identity-v1.sql ===
OK step 4
=== STEP 5/16: schema-sales-point-v1.sql ===
OK step 5
=== STEP 6/16: diff-party-franchise.sql ===
OK step 6
=== STEP 7/16: diff-core-auth-avancee.sql ===
OK step 7
=== STEP 8/16: schema-ref-static-v1.sql ===
OK step 8
=== STEP 9/16: schema-booking-v1.sql ===
OK step 9
=== STEP 10/16: schema-settlement-v1.sql ===
OK step 10
=== STEP 11/16: schema-cash-management-v1.sql ===
OK step 11
=== STEP 12/16: schema-invoicing-v1.sql ===
OK step 12
=== STEP 13/16: schema-product-catalogue-v1.sql ===
OK step 13
=== STEP 14/16: schema-pricing-v1.sql ===
OK step 14
=== STEP 15/16: schema-permissions-config-v1.sql ===
OK step 15
=== STEP 16/16: schema-provider-integration-v1.sql ===
OK step 16
=== TABLE COUNT ===
293
=== TRIGGERS MFA ===
            tgname             |          relname           
-------------------------------+----------------------------
 trg_config_protect_mfa_issuer | config_application_setting
 trg_core_mfa_require_issuer   | core_mfa_totp
(2 rows)
```

**Résultat : 16/16, 0 erreur, 293 tables.**

## Conclusion

**Conforme** pour les 4 colonnes présentes en base (defaults NULL sur parent booking +
partitions, address_type, minor_unit, instrument_tracking_mode) ; seeds devises OK ;
4/4 scénarios triggers PASS en harness ; chaîne reference/ **16/16 / 293 tables** après
correctif de placement.

**Écarts / notes (sans correction unilatérale) :**
1. `booking_payment`, `core_permission`, `config_application_setting`, `core_mfa_totp`
   absents en runtime — changements reference/ + triggers documentés, pas de migration
   ALTER/CREATE pour ces objets.
2. `phpcpd` non vert (clones préexistants).
3. Validation Application MFA non implémentable aujourd'hui (module absent) — à faire
   lors du chantier Core MFA.
