## Reprise à froid

Vérification runtime lecture seule de `booking_service_extension` sur
`ostravel-postgres-1` : schéma (`\d`), données (`sort_order`), présence
de `Version20260723120000` dans `doctrine_migration_versions`. Ferme le
doute « journal/git dit appliqué, mais base réelle ? ». Aucune écriture.

## Origine

```
TASK — Vérification runtime : booking_service_extension (état réel en base)

CONTEXTE
Le pilote DB a confirmé depuis le repo que la migration Version20260723120000
(ajout de sort_order sur booking_service_extension) est écrite et committée, et que
le journal la déclare appliquée. Ce qui n'est PAS vérifiable depuis git : l'état réel
de la base. Cette tâche ferme ce dernier doute. Aucune écriture, aucune modification —
lecture seule.

À EXÉCUTER (sur la DB du projet, container ostravel_postgres) :

1. \d booking_service_extension
2. SELECT code, label, sort_order FROM booking_service_extension ORDER BY sort_order;
3. SELECT version, executed_at FROM doctrine_migration_versions
   WHERE version LIKE '%20260723120000%';

ATTENDU (à comparer explicitement, ne pas se contenter de "ça a l'air bon") :
- Colonnes : code VARCHAR(30) PK | label VARCHAR(100) NOT NULL |
  sort_order SMALLINT NOT NULL DEFAULT 0 | created_at TIMESTAMPTZ NOT NULL DEFAULT now()
- 3 lignes : accommodation=0, transport_segment=1, car_rental=2
- Migration Version20260723120000 présente dans doctrine_migration_versions avec
  un executed_at non NULL

JOURNAL À RÉDIGER (obligatoire, conforme à
docs/decisions/2026-07-23-journal-convention-origine.md) :
  docs/journal/2026-07-23-booking-service-extension-verification-runtime.md
Avec les 3 blocs d'en-tête imposés :
  1. Reprise à froid (3-5 lignes) — pourquoi cette vérification existe, ce qu'elle ferme
  2. Origine — ce prompt collé VERBATIM, non reformulé
  3. Décisions prises — aucune décision n'est à prendre dans cette tâche (vérification
     pure). Écrire explicitement : "Aucune décision — tâche de vérification en lecture
     seule (demandée par le pilote DB architect)". Ne pas laisser le bloc vide.
Corps du journal : la sortie BRUTE des 3 commandes (pas un résumé narré) + une ligne de
conclusion : conforme / écart constaté (et lequel).

Puis git add / commit / push du journal.

À RENDRE dans ta réponse : la même sortie brute + la conclusion, pour remontée au
pilote DB.

Si un écart est constaté : le remonter tel quel, NE PAS le corriger — la correction sera
tranchée avec le pilote DB architect.
```

## Décisions prises

Aucune décision — tâche de vérification en lecture seule (demandée par le pilote DB architect).

---

# Journal — 2026-07-23 — booking_service_extension vérification runtime

Conteneur réel : `ostravel-postgres-1` (compose project `ostravel`).

## 1. `\d booking_service_extension`

```text
                Table "public.booking_service_extension"
   Column   |           Type           | Collation | Nullable | Default 
------------+--------------------------+-----------+----------+---------
 code       | character varying(30)    |           | not null | 
 label      | character varying(100)   |           | not null | 
 created_at | timestamp with time zone |           | not null | now()
 sort_order | smallint                 |           | not null | 0
Indexes:
    "booking_service_extension_pkey" PRIMARY KEY, btree (code)
Referenced by:
    TABLE "booking_service_type_extension" CONSTRAINT "booking_service_type_extension_extension_code_fkey" FOREIGN KEY (extension_code) REFERENCES booking_service_extension(code)
```

## 2. `SELECT code, label, sort_order FROM booking_service_extension ORDER BY sort_order;`

```text
       code        |               label                | sort_order 
-------------------+------------------------------------+------------
 accommodation     | Accommodation detail / hotel rooms |          0
 transport_segment | Transport segments                 |          1
 car_rental        | Car rental detail                  |          2
(3 rows)
```

## 3. `SELECT version, executed_at FROM doctrine_migration_versions WHERE version LIKE '%20260723120000%';`

```text
                 version                  |     executed_at     
------------------------------------------+---------------------
 DoctrineMigrations\Version20260723120000 | 2026-07-23 12:32:24
(1 row)
```

## Conclusion

**conforme** — colonnes (code PK VARCHAR(30), label VARCHAR(100) NOT NULL, sort_order SMALLINT NOT NULL DEFAULT 0, created_at TIMESTAMPTZ NOT NULL DEFAULT now()), 3 lignes (accommodation=0, transport_segment=1, car_rental=2), migration `Version20260723120000` présente avec `executed_at` non NULL (`2026-07-23 12:32:24`).
