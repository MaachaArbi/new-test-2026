## Reprise à froid

§34 — défaut de TVA par type de service (`invoicing_service_type_default_tax`).
Table côté Facturation, aucun seed, Booking inchangé. §69 ouvert (exonération
client). Chaîne 16/16, 300 tables. Push inclus.

## Origine

```
TASK — TVA : défaut par type de service (clôture partielle du §34)

La TVA est déjà largement construite (tax_calc_method, tax_rate_id, figement).
Il manque le taux proposé PAR DÉFAUT selon le type de service.

Décision placement : table côté Facturation, PAS de colonnes sur
booking_service_type (Booking = TTC ; ordre de chaîne).

Structure invoicing_service_type_default_tax (NOT NULL, absence de ligne =
aucun défaut). AUCUN SEED. Documenter legacy + résolution Domain dans
modele-conceptuel-facturation.md. Pas de PHP.

Clôturer §34, ouvrir §69 exonération client. Vérifs + journal + commit + PUSH.
```

## Décisions prises

- Table côté Facturation plutôt que colonnes sur `booking_service_type` — Booking
  n'a pas à connaître la TVA (utilisateur, 24/07)
- Valeurs en base, résolution dans le Domain (utilisateur, sur analyse architecte DB)
- Absence de ligne = absence de défaut, plutôt qu'une ligne à NULL (architecte DB)

---

# Journal — 2026-07-24 — TVA défaut par service (§34)

## Livrable

`invoicing_service_type_default_tax` dans `schema-invoicing-v1.sql` + trigger
`updated_at` (convention locale). Documentation modèle + clôture §34 / ouverture §69.

## Vérification 1 — chaîne 16/16

```text
=== STEP 1/16 … 16/16 === OK
300
```

## Vérification 2 — FK service_type (brut)

```text
ERROR:  insert or update on table "invoicing_service_type_default_tax" violates foreign key constraint "invoicing_service_type_default_tax_service_type_code_fkey"
DETAIL:  Key (service_type_code)=(not_a_real_service) is not present in table "booking_service_type".
```

## Vérification 3 — CHECK tax_calc_method (brut)

```text
ERROR:  new row for relation "invoicing_service_type_default_tax" violates check constraint "invoicing_service_type_default_tax_tax_calc_method_check"
DETAIL:  Failing row contains (hotel, 1, partial, …).
```

## Vérification 4 — booking inchangé

```text
git diff --stat -- reference/schemas/schema-booking-v1.sql → (vide)
```

## Qualité

```text
phpstan OK · deptrac 0 · phpunit 397 OK (2 notices préexistants)
```
