# Décision — party-account-group-extension.diff déjà fusionné dans schema-party-account-v1.sql

Date : 2026-07-21  
Contexte : import schéma Party + Core

## Instruction du prompt

Créer 4 migrations exécutant dans l’ordre :
1. `schema-ref-common.sql`
2. `schema-party-account-v1.sql`
3. `party-account-group-extension.diff`
4. `schema-core-identity-v1.sql`

## Constat (package `reference/` du 2026-07-21)

Le fichier `schema-party-account-v1.sql` contient **déjà** (lignes ~464–549) les objets
du `.diff` : `party_account_group_type`, `party_account_group`, `party_account_group_member`
(+ indexes / trigger / seed `commercial`).

Ré-exécuter le `.diff` converti en SQL après le schéma Party provoquerait une erreur
PostgreSQL du type `relation "party_account_group_type" already exists`.

## Alternatives envisagées

1. Exécuter le `.diff` tel quel → échec migrate (tables déjà là).
2. Modifier `reference/schemas/` pour retirer le doublon → **interdit** (`reference/` gelé).
3. Migration 3 en no-op versionnant le `.diff` (lecture + sha256) + assertion que les
   tables existent déjà via la migration 2 → retenu.

## Décision appliquée

Alternative **3** : `Version20260721110102` versionne le fichier `.diff` sans le
ré-appliquer. Les tables groupe sont créées par `Version20260721110101`.
