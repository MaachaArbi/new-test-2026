# Surveillance pg_partman — avance des tranches (§8)

Sans tranche DEFAULT et avec 3 mois d'avance, une automatisation en panne
non détectée conduit à un **arrêt des réservations** sous 3 mois.
La surveillance est **critique**, pas optionnelle.

## Seuil d'alerte recommandé

Alerter lorsque `months_of_advance < 2` sur **n'importe quelle** des
4 tables. Laisse le temps d'intervenir avant le rejet des écritures.

Ne pas construire le système d'alerte ici — requête seule, pour
supervision externe (cron utilisateur, Prometheus, etc.).

## Requête de contrôle

« Combien de mois d'avance reste-t-il sur chaque table partitionnée ? »

`months_of_advance` = nombre de mois calendaires **strictement après**
le mois courant encore couverts par une tranche existante
(borne haute exclusive de la tranche la plus lointaine).

```sql
WITH parents AS (
    SELECT unnest(ARRAY[
        'booking',
        'core_session',
        'core_auth_attempt',
        'provider_call_log'
    ]) AS parent_table
),
farthest AS (
    SELECT
        p.parent_table,
        MAX(
            to_date(
                regexp_replace(
                    c.relname,
                    '^.*_p([0-9]{8})$',
                    '\1'
                ),
                'YYYYMMDD'
            )
        ) AS last_partition_start
    FROM parents p
    LEFT JOIN pg_class parent
           ON parent.relname = p.parent_table
          AND parent.relkind = 'p'
    LEFT JOIN pg_namespace n
           ON n.oid = parent.relnamespace
          AND n.nspname = 'public'
    LEFT JOIN pg_inherits i
           ON i.inhparent = parent.oid
    LEFT JOIN pg_class c
           ON c.oid = i.inhrelid
          AND c.relname ~ '_p[0-9]{8}$'
    GROUP BY p.parent_table
)
SELECT
    parent_table,
    last_partition_start,
    CASE
        WHEN last_partition_start IS NULL THEN NULL
        ELSE (
            (EXTRACT(YEAR FROM (last_partition_start + INTERVAL '1 month'))::int
             - EXTRACT(YEAR FROM CURRENT_DATE)::int) * 12
            + (EXTRACT(MONTH FROM (last_partition_start + INTERVAL '1 month'))::int
               - EXTRACT(MONTH FROM CURRENT_DATE)::int)
            - 1
        )
    END AS months_of_advance,
    CASE
        WHEN last_partition_start IS NULL THEN 'MISSING_TABLE_OR_PARTITIONS'
        -- noms attendus : {parent}_pYYYYMMDD (convention pg_partman)
        WHEN (
            (EXTRACT(YEAR FROM (last_partition_start + INTERVAL '1 month'))::int
             - EXTRACT(YEAR FROM CURRENT_DATE)::int) * 12
            + (EXTRACT(MONTH FROM (last_partition_start + INTERVAL '1 month'))::int
               - EXTRACT(MONTH FROM CURRENT_DATE)::int)
            - 1
        ) < 2 THEN 'ALERT'
        ELSE 'OK'
    END AS status
FROM farthest
ORDER BY parent_table;
```

## Lecture

| `months_of_advance` | Action |
|---|---|
| `NULL` | Table absente ou aucune tranche `_yYYYYmMM` — investiguer |
| `< 2` | **ALERTE** — maintenance pg_partman / BGW probablement en panne |
| `≥ 2` | OK (cible nominale ≈ 3 avec `premake = 3`) |
