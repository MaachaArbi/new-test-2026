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
