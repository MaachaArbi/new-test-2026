## Reprise à froid

Alignement backend sur le retrait de `is_vat_subject` (schéma Party 24/07,
commits `b73a9f3` / `17e0f0e`). Aucun équivalent réintroduit dans l'entité —
l'info vit dans `party_account_tax_exemption`. Pas de migration. Push inclus.

## Origine

```
TASK — Backend : aligner le code sur le retrait de is_vat_subject

Le balayage Party du 24/07 a supprimé
party_account_organization_identity.is_vat_subject du schéma de référence.
Ne PAS remplacer isVatSubject par un équivalent dans l'entité.
Retirer : entity, command, handler, ORM XML, bootstrap (+ log), tests.
AUCUNE migration. Vérifs : grep 0 ; qualité complète ; bootstrap.
JOURNAL + commit + PUSH.
```

## Décisions prises

- L'assujettissement TVA devient une exonération datée dans
  `party_account_tax_exemption`, pas un booléen sur l'identité organisation
  (utilisateur, 24/07)
- Aucun équivalent réintroduit dans l'entité — l'information relève d'un
  autre agrégat (architecte DB)
- Pas de migration runtime malgré la colonne encore présente en base
  (DEFAULT false) — consignes explicites de la tâche (architecte DB /
  utilisateur via le prompt)

---

# Journal — 2026-07-24 — alignement isVatSubject

## Fichiers touchés (recensement réel)

| Fichier | Occurrences retirées |
|---|---|
| `PartyAccountOrganizationIdentity.php` | constructeur, factory, getter |
| `SetPartyAccountOrganizationIdentityCommand.php` | propriété |
| `SetPartyAccountOrganizationIdentityHandler.php` | passage |
| `BootstrapAgencyAccountCommand.php` | constante, arg, message de log |
| `PartyAccountOrganizationIdentity.orm.xml` | field |
| `PartyAccountOrganizationIdentityTest.php` | args + assertions |
| `PartyAccountOrganizationIdentityAndOfficePersistenceTest.php` | args + assertions |

## Vérification migrations

`grep is_vat_subject migrations/` → **0**. Aucune migration ne crée ni ne
supprime la colonne.

## Constataion runtime (écart vs hypothèse du prompt)

La table `party_account_organization_identity` **existe** en runtime et porte
encore `is_vat_subject boolean NOT NULL DEFAULT false`. Doctrine n'écrit plus
la colonne (non mappée) → le DEFAULT DB s'applique. **Aucune migration écrite**
(consigne explicite). Colonne orpheline inertée tant qu'un DROP dédié n'est
pas demandé.

## Vérification grep

```text
grep -rn 'isVatSubject\|is_vat_subject\|vatSubject' src/ tests/ config/ migrations/
→ 0
```

## Tests unitaires — vacuité

Aucun test vidé : `create_stores_all_fields…` et
`create_accepts_all_null_optional_fields` restent porteurs (autres champs).
Assertions `isVatSubject` retirées uniquement.

## Qualité / bootstrap

```text
Avant  : PHPUnit 397 tests, 2678 assertions (dernière suite connue)
Après  : PHPUnit 397 tests, 2676 assertions (−2 : assertions isVatSubject
         retirées, + assertNull optionnels compensatoires dans le test unit)
phpstan OK · deptrac 0 · php-cs-fixer appliqué sur les fichiers touchés
```

Bootstrap `app:party:bootstrap-agency` :

```text
[WARNING] Compte agence déjà présent (id=9, …) — aucune création.
[NOTE] organization_identity déjà présente pour account_id=9 — skip.
[NOTE] office déjà présent pour account_id=9 — skip.
```

## Push

Confirmé dans le rapport de clôture.

---

# Suppression de la colonne orpheline is_vat_subject — ARRÊT (donnée métier détectée)

## Origine de cette suite

```
TASK — Supprimer la colonne orpheline is_vat_subject en runtime

Cause de l'erreur à retenir : Party/Core ont été mis en base par IMPORT DIRECT
DES FICHIERS SQL (docs/journal/2026-07-21-import-schema-party-core.md), pas par
des migrations Doctrine. Chercher dans migrations/ ne peut donc rien montrer
sur l'état réel du runtime — seule une requête sur la base le dit.

AVANT de supprimer, vérifier : colonne existe, données significatives (si des
lignes portent true → SIGNALER ET S'ARRÊTER, ne pas supprimer), dépendances
(vue/index/contrainte). Balayage plus large runtime ↔ reference/schemas pour
party_account* et core_* — RAPPORTER sans corriger.
```

## Vérification 1 — la colonne existe bien

```sql
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_name='party_account_organization_identity' AND column_name='is_vat_subject';
```

```text
 column_name    | data_type | is_nullable | column_default
----------------+-----------+-------------+----------------
 is_vat_subject | boolean   | NO          | false
```

Confirmé : la colonne existe en runtime, `NOT NULL DEFAULT false`.

## Vérification 2 — distribution des valeurs → ARRÊT

```sql
SELECT is_vat_subject, count(*) FROM party_account_organization_identity GROUP BY 1;
```

```text
 is_vat_subject | count
----------------+-------
 f              |     2
 t              |    85
```

**87 lignes au total, dont 85 à `true` (97,7 %).** Ce n'est pas « la valeur par
défaut jamais renseignée » anticipée par le prompt — c'est l'inverse : la quasi-
totalité des identités organisation porte l'information « assujetti à la TVA ».

**Conformément à la consigne explicite de la tâche : ARRÊT. La colonne n'est
PAS supprimée.** Suppression = perte silencieuse d'une information métier réelle
pour 85 tiers. Avant tout DROP, cette information devrait être reportée dans
`party_account_tax_exemption` (le modèle cible actuel, cf. exonération datée),
probablement sous la forme inverse : absence de ligne d'exonération `vat` =
assujetti — mais **la règle de correspondance exacte (assujetti=true ↔ quoi
précisément dans tax_exemption) est une décision métier, pas une déduction
technique.** Remontée au pilote DB / architecte DB, pas devinée ici.

## Vérification 3 — dépendances (pour mémoire, complétée malgré l'arrêt)

- Aucune vue ne dépend de la colonne (`pg_depend` / `pg_rewrite` — 0 résultat).
- Aucun index, aucune contrainte dédiée sur `is_vat_subject` (`\d
  party_account_organization_identity` — seules les 3 FK `account_id` /
  `created_by` / `updated_by` et la PK existent).
- 87 lignes au total dans la table.

## Décision

**Ne rien supprimer.** La tâche prescrivait explicitement ce comportement en
cas de données `true` significatives. Le prompt initial avait supposé (à tort)
que la colonne serait inerte — elle ne l'est pas.

## Balayage plus large — écarts runtime ↔ reference/schemas (Party + Core)

Comparaison de `information_schema.columns` (base réelle `ostravel`) avec les
`CREATE TABLE` de `reference/schemas/schema-party-account-v1.sql`,
`diff-party-franchise.sql`, `schema-core-identity-v1.sql` et
`diff-core-auth-avancee.sql`. **Rapporté ci-dessous, rien corrigé.**

### Cause racine confirmée

Party + Core ont été importés en base le 21/07 depuis 4 fichiers seulement
(`schema-ref-common.sql`, une version antérieure de
`schema-party-account-v1.sql`, `party-account-group-extension.diff`,
une version antérieure de `schema-core-identity-v1.sql`). Depuis, `reference/`
a évolué (sync 44 fichiers le 23/07, plusieurs balayages Party le 24/07 —
exonérations, plafonds, affectations, politique commerciale, franchise, retrait
approbation, devises par défaut, Auth avancée Core...). La vérification
« chaîne 16/16 » de ces évolutions tourne systématiquement sur une base
jetable dédiée (`ostravel_chain_verify`), **jamais répercutée en migration sur
le runtime applicatif réel (`ostravel`)**. L'écart n'est donc pas isolé à
`is_vat_subject` : il est **systémique**, daté du 21/07, et s'est élargi à
chaque évolution de `reference/` depuis.

### Party — tables entièrement absentes du runtime (9)

`reference/` en définit 28 (27 dans `schema-party-account-v1.sql` + 1 dans
`diff-party-franchise.sql`), le runtime en a 19. Manquent :

| Table | Source |
|---|---|
| `party_tax_exemption_type` | `schema-party-account-v1.sql` |
| `party_tax_exemption_type_translation` | idem |
| `party_account_tax_exemption` | idem |
| `party_assignment_type` | idem |
| `party_assignment_type_translation` | idem |
| `party_account_manager_assignment` | idem |
| `party_account_credit_limit` | idem |
| `party_account_commercial_policy` | idem |
| `party_account_franchise` | `diff-party-franchise.sql` |

### Party — colonnes manquantes dans des tables existantes

| Table | Colonnes présentes en référence, absentes en runtime |
|---|---|
| `party_account` | `display_currency_code`, `billing_currency_code` (VARCHAR(3) REFERENCES ref_currency) |
| `party_account_organization_identity` | `accounting_account_code`, `third_party_account_code` (export comptable) |

### Party — colonnes orphelines en runtime, absentes de référence (sens inverse d'`is_vat_subject`)

| Table | Colonnes présentes en runtime, retirées de référence le 24/07 | Données |
|---|---|---|
| `party_account_organization_identity` | `is_vat_subject` | 85 `true` / 2 `false` — **traité ci-dessus, ARRÊT** |
| `party_account_office_relation` | `is_approved`, `approved_at`, `approved_by` | 0 ligne renseignée (les 3 colonnes) — sans risque de perte si un DROP futur est décidé, mais **non fait ici** |

### Party — drift de données (hors périmètre strict « colonnes », signalé quand même)

`party_account_group_type` : seed runtime = `commercial (0)`, `zone (1)` ;
seed référence actuelle = `contracting (0)`, `pricing (1)`, `collection (2)`,
`reporting (3)`. Les deux jeux sont totalement disjoints — même mécanisme de
désynchronisation, au niveau des données de référence cette fois, pas des
colonnes.

### Core — écart le plus large des deux modules

Runtime : **1 seule table** (`core_credential`). Référence (`schema-core-
identity-v1.sql` + `diff-core-auth-avancee.sql`) : **7 tables logiques**
(partitions filles de `core_session`/`core_auth_attempt` non comptées à part).

| Élément référence | Statut runtime |
|---|---|
| `core_credential.failed_login_count`, `.locked_until` | Colonnes absentes |
| `core_credential` → FK vers `core_credential_provider` | Absente (colonne `provider` toujours VARCHAR libre) |
| `core_credential_provider` | Table absente |
| `party_role_security_policy` | Table absente |
| `core_session` (+ partitions) | Table absente |
| `core_auth_attempt` (+ partitions) | Table absente |
| `core_mfa_totp` | Table absente |
| `core_mfa_recovery_code` | Table absente |

C'est cohérent avec la note déjà présente dans `migrations/Version20260724170000.php`
(« core_session / core_auth_attempt / provider_call_log absentes en runtime »)
— déjà su pour ce cas précis, mais pas généralisé jusqu'à ce balayage.

### Ce qui n'a PAS été trouvé en écart

Aucune divergence de colonnes détectée sur : `party_role`, `party_role_translation`,
`party_address_type`, `party_address_type_translation`, `party_account_address`,
`party_account_role`, `party_account_person_identity`, `party_function`,
`party_function_translation`, `party_account_function`, `party_account_attribute`,
`party_account_document`, `party_account_office`, `party_account_group`,
`party_account_group_member`. Ces 15 tables sont alignées colonne à colonne.

## Vérification chaîne reference/ 16/16 (contrôle de non-régression, aucun fichier reference/ modifié dans cette tâche)

Base jetable `ostravel_chain_verify`, `ON_ERROR_STOP=1`, les 16 fichiers dans
l'ordre habituel :

```text
=== STEP 1/16 … 16/16 === OK (aucune erreur)
=== TABLE COUNT ===
309
```

**Écart signalé pour mémoire, non investigué ici** : le dernier chiffre
documenté dans l'historique (`docs/journal/2026-07-24-party-corrections-
balayage.md`, commit `17e0f0e`) était **308**, sur un état de `reference/`
identique à l'actuel (aucun commit touchant `reference/schemas/` depuis
`17e0f0e` ; le commit `676ebd0` — alignement `is_vat_subject` côté backend —
n'a touché aucun fichier de `reference/schemas/`). Le delta de 1 table entre
ma mesure et la dernière valeur journalisée **précède cette tâche** (aucun
fichier `reference/` modifié ici) ; signalé au pilote DB pour arbitrage, pas
réinvestigué en profondeur — hors périmètre de la tâche courante.

## Suite qualité

Aucun code applicatif ni migration modifié dans cette suite (la suppression a
été bloquée avant écriture). `phpstan`/`deptrac`/`phpcpd`/`phpunit` restent à
l'état de la clôture précédente — non ré-exécutés, rien n'a changé côté code.

## Leçon de méthode (à retenir)

**L'état du runtime ne se déduit JAMAIS de `reference/schemas/` ni de
`migrations/` par simple lecture — il se VÉRIFIE PAR REQUÊTE sur la base
réelle**, tant qu'un module a pu être importé par SQL direct plutôt que par
migration Doctrine incrémentale (cas de Party et Core, importés le 21/07).
Deuxième leçon, révélée par ce balayage : la vérification « chaîne 16/16 »
elle-même ne prouve QUE la cohérence interne de `reference/schemas/` — elle ne
dit RIEN sur l'état du runtime applicatif si elle tourne sur une base jetable
dédiée (`ostravel_chain_verify`) et non sur `ostravel`. Un « 16/16 vert » a pu
être annoncé à répétition dans des journaux précédents sans jamais garantir
que le runtime suivait. Les deux vérifications sont complémentaires, pas
substituables l'une à l'autre.

## Push

Commit de documentation uniquement (aucun code, aucune migration — la
suppression prescrite n'a pas été exécutée). Confirmé dans le rapport de
clôture ci-dessous.
