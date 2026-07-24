## Reprise à froid

Balayage Party V1.5 — confrontation legacy : devises, comptes export,
exonérations, affectations, plafond, politique commerciale + raison
`account_policy`. §2/§69 clos, §70 ouvert. 308 tables. Push inclus.

## Origine

```
TASK — Balayage du module Party : appliquer les décisions issues de la
confrontation au legacy

6 à construire : comptes comptables ; 2 devises ; exonérations (+ retrait
is_vat_subject) ; affectations responsables ; plafond ; politique commerciale ;
raison booking account_policy ; corrections documentaires.

Placement Party (pas Facturation/Règlements). Clôturer §2 §69, MAJ §14,
ouvrir §70 devise produit. Vérifs + journal + commit + PUSH.
```

## Décisions prises

- Les 10 suppressions legacy (utilisateur, 24/07)
- Deux colonnes de devise distinctes (utilisateur, 24/07)
- Exonérations indépendantes couvrant toute l'activité ; suppression de
  `is_vat_subject` (utilisateur, 24/07)
- Affectations multiples et globales (utilisateur, 24/07)
- Plafond global par devise sans ventilation par service (utilisateur, 24/07)
- Politique commerciale en colonnes explicites (utilisateur, 24/07)
- Table d'affectations distincte de `party_account_function` (architecte DB)
- Une seule table pour plafond permanent et temporaire via `valid_to`
  (architecte DB)
- Placement des exonérations et du plafond dans Party (architecte DB)

---

# Journal — 2026-07-24 — balayage Party

## Écart signalé (non corrigé)

`is_vat_subject` retiré du schéma de référence, mais le backend s'y appuie encore :

- `PartyAccountOrganizationIdentity` (+ getter)
- `SetPartyAccountOrganizationIdentityCommand` / Handler
- mapping Doctrine XML
- `BootstrapAgencyAccountCommand`
- tests unitaires + intégration

Runtime inchangé (pas de migration Doctrine dans cette tâche) → qualité PHP verte
sur la base runtime. Alignement PHP = tâche backend suivante.

## Vérification 1 — chaîne

```text
16/16 OK
308 tables
is_vat_subject count = 0
```

## Vérification 2 — intégrité (brut)

```text
ERROR: … violates foreign key constraint "party_account_tax_exemption_exemption_type_code_fkey"
DETAIL: Key (exemption_type_code)=(not_a_type) is not present in table "party_tax_exemption_type".

ERROR: … violates check constraint "chk_party_account_tax_exemption_period"
DETAIL: Failing row contains (…, 2026-07-01, 2026-07-01, …).

ERROR: … violates check constraint "party_account_credit_limit_amount_minor_check"
DETAIL: Failing row contains (…, TND, 0, …).

credit_limits = 2
commercial_managers = 2

 account_policy | ar | سياسة الحساب التجارية
 account_policy | en | Account commercial policy
 account_policy | fr | Politique commerciale du compte
```

## Qualité

```text
phpstan OK · deptrac 0 · phpunit 397 OK (2 notices préexistants)
```
