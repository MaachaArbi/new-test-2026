# Décision — pg_partman intégré à la procédure de déploiement (§8)

**Date :** 2026-07-24  
**Statut :** adopté  
**Tag :** (architecte DB) + décisions utilisateur 24/07

## Contexte

Quatre tables sont découpées par mois (`booking`, `core_session`,
`core_auth_attempt`, `provider_call_log`). Les tranches bootstrap sont
**figées** dans les schémas de référence (ex. `y2026m07`…`y2026m09`).
Sans tranche DEFAULT, un déploiement en 2027 avec ces seules tranches
**rejette** la première écriture hors bornes.

## Décision

1. **La mise en place de pg_partman est une étape OBLIGATOIRE de la
   procédure de déploiement**, exécutée **après** la chaîne des schémas
   et **avant** la première utilisation applicative — pas une tâche de
   maintenance ultérieure.

2. **Pas de tranche DEFAULT** : panne bruyante assumée (utilisateur,
   24/07). Une tranche manquante doit provoquer un rejet immédiat.

3. Paramètres actés :

| Table | Avance | Rétention pg_partman |
|---|---|---|
| `booking` | 3 mois | AUCUNE (historique commercial / fiscal) |
| `core_session` | 3 mois | 3 mois puis DROP |
| `core_auth_attempt` | 3 mois | 3 mois puis DROP |
| `provider_call_log` | 3 mois | AUCUNE — purge applicative (RÉVISABLE) |

4. Règle métier `provider_call_log` (**RÉVISABLE**, utilisateur 24/07) :
   un journal d'appel rattaché à une réservation n'est **jamais**
   supprimé ; seuls les appels n'ayant pas abouti sont purgés après un
   mois **par l'application**, pas par pg_partman.

## Procédure (ordre)

```bash
# 1. Schémas (chaîne 16 étapes) — voir reference/meta/00-INDEX.md
# 2. Extension + enregistrement des 4 tables
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 \
  -f docker/postgres/sql/drop_default_partitions.sql \
  -f docker/postgres/sql/pg_partman_setup.sql
# 3. Vérifier part_config (rétention NULL sur booking / provider_call_log)
# 4. Première utilisation applicative seulement après (2)+(3)
```

Image Docker : `docker/postgres/Dockerfile` (PG16 + pg_partman 5.2.4).
Maintenance : cron horaire dans le conteneur
(`SELECT partman.run_maintenance(...)` — `docker/postgres/crontab`).
Porte ouverte pour des crons de vérification externes (hors périmètre
de cette décision). `pg_cron` / BGW non retenus ici (paquet Alpine
partman incompatible PG16 ; BGW bloqué par LLVM bitcode de l'image).

Surveillance : `docs/ops/pg-partman-surveillance.md` (seuil d'alerte :
moins de 2 mois d'avance).
