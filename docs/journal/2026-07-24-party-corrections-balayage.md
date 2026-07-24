## Reprise à froid

Party V1.6 — trois corrections issues de la fin du balayage (suite de
`b73a9f3`) : retrait de l'approbation bureau↔tiers ; plafond ventilé par
service (découvert seul, FK différée) ; types de groupe réels (option a).
308 tables inchangé. Push inclus. Backend `is_vat_subject` hors périmètre.

## Origine

```
TASK — Party : corrections issues de la fin du balayage (suite du commit b73a9f3)

1. RETIRER L'APPROBATION DU RATTACHEMENT BUREAU ↔ TIERS
   Supprimer is_approved, approved_at, approved_by sur
   party_account_office_relation. Conserver le rattachement + période.

2. PLAFOND PAR SERVICE — service_type_code sur party_account_credit_limit
   (NULL = tous produits). FK différée depuis schema-booking-v1.sql.
   Ligne la plus précise l'emporte ; seul le découvert est ventilé.

3. TYPES DE GROUPE — seeder contracting, pricing, collection, reporting.
   Option (a) : retirer 'commercial' + adapter pricing-test-data.sql.

VÉRIFS : chaîne 16/16 → 308 ; colonnes approbation = 0 ; FK rejet ;
3 plafonds acceptés ; types seedés ; pricing-test-data OK ; qualité verte.
JOURNAL + INDEX V1.6 + §2 nuance + commit + PUSH.
```

## Décisions prises

- Retrait de l'approbation sur `party_account_office_relation`
  (utilisateur, 24/07) — jamais pratiquée ; aurait bloqué création+résa
- Plafond ventilé par service sur le **découvert seul** ; ligne la plus
  précise remplace ; solde global partagé (utilisateur, 24/07)
- Quatre types de groupe : `contracting`, `pricing`, `collection`,
  `reporting` (utilisateur, 24/07)
- FK différée `service_type_code` → `booking_service_type` posée dans
  Booking (architecte DB) — même motif que `cash_instrument_location`
- Option **(a)** : retirer `'commercial'` et adapter
  `pricing-test-data.sql` vers `'pricing'` (architecte DB) — le jeu de
  test doit refléter les types réels ; un type inutilisé finit utilisé
  par erreur

---

# Journal — 2026-07-24 — Party corrections fin de balayage (V1.6)

## Hors périmètre (rappel)

Alignement backend PHP sur le retrait de `is_vat_subject` (entity,
command/handler, mapping Doctrine, bootstrap, tests) — écart signalé
au commit `b73a9f3`, tâche backend dédiée. Non traité ici.

## Vérification 1 — chaîne

```text
=== STEP 1/16 … 16/16 === OK
tables = 308
```

## Vérification 2 — colonnes d'approbation absentes

```text
SELECT count(*) FROM information_schema.columns
WHERE column_name IN ('is_approved','approved_at','approved_by');
→ 0
```

## Vérification 3 — FK différée (rejet brut)

```text
ERROR:  insert or update on table "party_account_credit_limit" violates foreign key constraint "fk_party_account_credit_limit_service_type"
DETAIL:  Key (service_type_code)=(not_a_service) is not present in table "booking_service_type".
```

## Vérification 4 — 3 plafonds nominaux acceptés

```text
INSERT 0 3
 id | currency_code | service_type_code | amount_minor | permanent
----+---------------+-------------------+--------------+-----------
  2 | TND           |                   |      1000000 | t
  3 | TND           | hotel             |       500000 | t
  4 | TND           | flight            |       200000 | f
```

## Vérification 5 — types de groupe

```text
 contracting | 0
 pricing     | 1
 collection  | 2
 reporting   | 3
```

## Vérification 6 — pricing-test-data.sql

```text
OK — groupe seedé : pricing / Groupe Amicale 1
```

## Qualité

```text
phpstan OK (0 errors)
deptrac 0 violations
PHPUnit 397/397 OK (2 notices préexistants)
phpcpd : clones préexistants acceptés (STATUS)
```

## Clôture doc

- `sujets-reportes.md` §2 : nuance « découvert ventilé, pas l'encours »
- `00-INDEX.md` : Party → V1.6
- `modele-conceptuel-party.md` / `party.md` alignés

## Push

Confirmé après commit (voir rapport de clôture).
