## Reprise à froid

Journal — 2026-07-22 — Alignement schéma Cash routing.
Lors de la vague précédente, `reference/schemas/schema-cash-management-v1.sql`
était **absent** du dépôt. Une slice a été **reconstruite** à partir du modèle
conceptuel. C’était une erreur de process.

## Origine

```
# TASK — Aligner la migration Cash Management sur le vrai schéma (re-synchronisé)

## Contexte
reference/schemas/schema-cash-management-v1.sql a été reconstruit par
erreur lors de la vague précédente (fichier absent du dépôt à ce
moment-là) — cette reconstruction divergeait du vrai schéma sur deux
points techniques (VARCHAR(20) vs VARCHAR(30)) et omettait le seed complet
des 10 modes de paiement. Le fichier de référence vient d'être
re-synchronisé avec la vraie source.

## Corrections à appliquer

1. Migration Version20260722200000.php : corriger VARCHAR(30) → VARCHAR(20)
   sur les deux colonnes concernées (cash_routing_type.code,
   cash_payment_method_routing.routing_type_code) — vérifier aussi le
   mapping Doctrine XML (CashRoutingType.orm.xml) si la longueur y est
   déclarée, pour rester cohérent

2. Ajouter le seed complet de cash_payment_method_routing (les 10 lignes
   par SELECT sur reglement_payment_method.code, incluant les commentaires
   (*) sur V/VE/RC/RI marqués "à confirmer avec l'utilisateur" — les
   reprendre tels quels dans le journal, ne pas les considérer comme
   définitifs)

3. Retirer le ON CONFLICT DO NOTHING ajouté sur le premier INSERT — la
   vraie source n'en a pas, cohérent avec le principe "les migrations
   n'improvisent pas sur le schéma figé"

4. Si des données de test ont déjà été insérées en base avec l'ancien
   schéma (VARCHAR(30)), vérifier si une nouvelle migration de correction
   est nécessaire ou si on peut encore modifier la migration existante
   (base de dev, pas de contrainte de production) — trancher et documenter

## Documentation
docs/journal/2026-07-22-cash-payment-method-routing-schema-fix.md :
expliquer précisément l'écart trouvé (les 2 divergences de type + le
seed manquant), la cause (fichier de référence absent au moment de la
vague), et rappeler explicitement la règle déjà établie sur Règlements
plus tôt aujourd'hui : ne JAMAIS reconstruire un schéma manquant, TOUJOURS
s'arrêter et demander — cette règle n'a pas été suivie ici, à ne plus
reproduire.

## Tests
Ajouter un test qui vérifie que les 10 lignes de seed existent bien après
migration, avec le bon routing_type_code pour chaque mode.

Relance phpstan/deptrac/phpcpd/phpunit. Pousse sur main, donne-moi juste
la liste des fichiers modifiés — je vérifierai directement via
raw.githubusercontent.com.
```

## Décisions prises

Décisions attribuées :
- Mandat de décision délégué par le prompt d'origine (Cursor — à valider)

---

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
   les modes Règlements via `SELECT … WHERE code IN (…)` (**11** modes :
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

**4ème défaut raisonnable (hors liste (*) du schéma, posé ici)** :

- (*) **PE** (paiement électronique) → `aucun` / `not_applicable`
  (même groupe que AD/CB — scriptural, pas de transit caisse). Déduction
  conceptuelle, à confirmer avec l’utilisateur au fil de l’usage.
  Une suppression erronée (confusion « 10 modes ») a été annulée :
  `reglement_payment_method` seed **11** codes (dont PE).

## Tests

`test_seeded_payment_method_routing_matches_schema` vérifie les **11** modes
seedés et leur `routing_type_code`.

## Qualité

- phpstan : OK
- deptrac : 0
- phpunit : 377 tests, 2531 assertions (2 notices préexistants)
- phpcpd : clone HttpSupport accepté (inchangé)
