## Reprise à froid

Rattrapage infra : `cash_session_balance` + trigger de refresh omis lors
de la migration `cash_movement`. Backfill des mouvements déjà présents.
Comptage/validation session **non** construits (différés frontend).

## Origine

```
# TASK — Cash Management : rattraper cash_session_balance (infra seule, pas de métier)

## Contexte
Trou trouvé le 23/07 : reference/schemas/schema-cash-management-v1.sql §5
(lignes 272-305) définit cash_session_balance + le trigger
trg_cash_movement_balance_refresh (AFTER INSERT ON cash_movement) — non
migrés avec cash_movement (Version20260723130000). Résultat : les
mouvements déjà insérés via cash_receive_instrument n'ont jamais alimenté
ce snapshot.

Le comptage/validation (cash_count_session_currency, cash_validate_session)
est DIFFÉRÉ — décision utilisateur du 23/07, jusqu'au chantier frontend qui
définira le vrai contrat API. NE PAS les construire dans cette tâche.

## Migration
Copier depuis reference/schemas/schema-cash-management-v1.sql lignes
272-305 : cash_session_balance (table) + cash_session_balance_refresh()
(fonction) + trg_cash_movement_balance_refresh (trigger). Exactement ce
bloc, rien d'ajouté.

## Rattrapage des données déjà existantes (OBLIGATOIRE)
Après création de la table, backfill en une seule requête depuis
cash_movement existant :

INSERT INTO cash_session_balance (session_id, currency_code, balance_minor, last_movement_id, movement_count, updated_at)
SELECT session_id, currency_code, SUM(amount_minor),
       (ARRAY_AGG(id ORDER BY id DESC))[1], COUNT(*), now()
FROM cash_movement
GROUP BY session_id, currency_code;

Vérifie après coup : SELECT * FROM cash_session_balance — le nombre de
lignes doit correspondre au nombre de couples (session_id, currency_code)
distincts déjà présents dans cash_movement. Colle cette vérification brute
dans le journal.

## Portée
UNIQUEMENT cette migration + son backfill. Aucun Domain, aucune Application,
aucun Handler, aucun test métier, aucun endpoint — pas de code PHP du tout
dans cette tâche. Le comptage/validation reste différé.

## Documentation
- docs/journal/2026-07-2X-cash-session-balance-backfill.md — convention du
  23/07 (Reprise à froid / Origine / Décisions attribuées), AVEC preuve
  brute collée (sortie SQL réelle de la vérification de backfill — pas
  juste un résumé chiffré, cf. ce qu'on a décidé d'exiger systématiquement)
- docs/decisions/2026-07-23-cash-count-validate-differe.md — nouveau
  fichier documentant la mise en pause du comptage/validation jusqu'au
  chantier frontend (même gabarit que la décision Règlements du même jour)
- docs/backlog/todo.md : ligne comptage/validation marquée différée,
  renvoyant vers cette décision

## Remontée
Pousse sur main, donne-moi le nom du commit.
```

## Décisions prises

- Rattraper `cash_session_balance` + trigger maintenant (infra) (utilisateur)
- Différer comptage/validation jusqu’au chantier frontend (utilisateur)
- Backfill obligatoire depuis `cash_movement` existant (utilisateur)
- Aucun code PHP Domain/Application dans cette vague (utilisateur)

---

# Journal — 2026-07-23 — cash_session_balance backfill

## Migration

`migrations/Version20260723140000.php` — SQL §5 copié depuis
`reference/schemas/schema-cash-management-v1.sql` (lignes 272-305) +
backfill `INSERT … SELECT … GROUP BY session_id, currency_code`.

## Preuve brute — vérification post-backfill

```text
 balance_rows 
--------------
           16
(1 row)

 movement_pairs 
----------------
             16
(1 row)

 session_id | currency_code | balance_minor | last_movement_id | movement_count |          updated_at           
------------+---------------+---------------+------------------+----------------+-------------------------------
         36 | TND           |         12500 |                1 |              1 | 2026-07-23 16:13:33.227961+00
         44 | TND           |          3000 |                2 |              1 | 2026-07-23 16:13:33.227961+00
         45 | TND           |          4000 |                4 |              1 | 2026-07-23 16:13:33.227961+00
         46 | TND           |          4000 |                5 |              1 | 2026-07-23 16:13:33.227961+00
         48 | TND           |         12500 |                6 |              1 | 2026-07-23 16:13:33.227961+00
         56 | TND           |          3000 |                7 |              1 | 2026-07-23 16:13:33.227961+00
         57 | TND           |          4000 |                9 |              1 | 2026-07-23 16:13:33.227961+00
         58 | TND           |          4000 |               10 |              1 | 2026-07-23 16:13:33.227961+00
         60 | TND           |         12500 |               11 |              1 | 2026-07-23 16:13:33.227961+00
         68 | TND           |          3000 |               12 |              1 | 2026-07-23 16:13:33.227961+00
         69 | TND           |          4000 |               14 |              1 | 2026-07-23 16:13:33.227961+00
         70 | TND           |          4000 |               15 |              1 | 2026-07-23 16:13:33.227961+00
         79 | TND           |         12500 |               16 |              1 | 2026-07-23 16:13:33.227961+00
         87 | TND           |          3000 |               17 |              1 | 2026-07-23 16:13:33.227961+00
         88 | TND           |          4000 |               19 |              1 | 2026-07-23 16:13:33.227961+00
         89 | TND           |          4000 |               20 |              1 | 2026-07-23 16:13:33.227961+00
(16 rows)
```

`balance_rows` (16) = `movement_pairs` distincts `(session_id, currency_code)` (16).

Trigger confirmé : `trg_cash_movement_balance_refresh` présent ;
fonction `cash_session_balance_refresh` présente.

## Hors périmètre

`cash_count_session_currency`, `cash_validate_session`, Domain/Application/HTTP.
