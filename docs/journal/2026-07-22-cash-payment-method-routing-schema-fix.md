# Journal — 2026-07-22 — Alignement schéma Cash routing

## Cause

Lors de la vague précédente, `reference/schemas/schema-cash-management-v1.sql`
était **absent** du dépôt. Une slice a été **reconstruite** à partir du modèle
conceptuel. C’était une erreur de process.

**Règle déjà établie (Règlements, même journée) : ne JAMAIS reconstruire un
schéma manquant — TOUJOURS s’arrêter et demander.** Cette règle n’a pas été
suivie ici ; à ne plus reproduire.

Le fichier de référence a depuis été re-synchronisé avec la vraie source.

## Écarts trouvés (reconstruction vs vrai schéma)

1. **VARCHAR(30) vs VARCHAR(20)** sur `cash_routing_type.code` et
   `cash_payment_method_routing.routing_type_code` (vrai schéma = 20).
2. **Seed `cash_payment_method_routing` manquant** — le vrai schéma seed
   tous les modes Règlements via `SELECT … WHERE code IN (…)` (11 lignes :
   AD/CB/PE, C/LC, E, PC, V, VE, RC/RI).
3. **`ON CONFLICT DO NOTHING`** improvisé sur l’INSERT `cash_routing_type`
   — absent de la source figée.
4. Labels / COMMENT ON TABLE divergents (textes simplifiés vs source).

## Correction appliquée

- `Version20260722200000` **réécrite** pour coller au §1 ROUTING (installs neuves).
- `Version20260722201000` **ajoutée** pour les bases de **dev** déjà migrées avec
  la version divergente : `ALTER … TYPE VARCHAR(20)`, labels alignés,
  `TRUNCATE` + seed officiel. Pas de DROP des tables (état partagé préservé).
- Mapping Doctrine : `length="20"` sur `CashRoutingType.code` et
  `routingTypeCode`.

## Seed — choix marqués (*) à confirmer

Repris tels quels du schéma (pas définitifs) :

- (*) **V** (virement) → `banque_directe` / `individual`
- (*) **VE** (versement espèce) → `banque_directe` / `individual`
- (*) **RC** / **RI** (retenue / ristourne) → `aucun` / `not_applicable`

## Tests

`test_seeded_payment_method_routing_matches_schema` vérifie les 11 modes
seedés et leur `routing_type_code`.

## Qualité

- phpstan : OK
- deptrac : 0
- phpunit : 377 tests, 2531 assertions (2 notices préexistants)
- phpcpd : clone HttpSupport accepté (inchangé)
