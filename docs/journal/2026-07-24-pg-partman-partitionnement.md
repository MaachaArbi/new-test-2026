## Reprise à froid

§8 — automatisation des tranches via pg_partman + suppression des tranches
DEFAULT (panne bruyante). Extension 5.2.4, avance 3 mois, rétention selon
tableau acté. Chaîne 16/16, 299 tables. Push inclus.

## Origine

```
TASK — §8 : automatisation des tranches (pg_partman) + suppression des tranches par défaut

CONTEXTE ET ORIGINE
Quatre tables sont découpées par mois : booking, core_session, core_auth_attempt,
provider_call_log. Leurs tranches ont été créées À LA MAIN (juillet, août, septembre 2026) et
rien ne crée les suivantes. Point identifié comme BLOQUANT AVANT PRODUCTION dans le backlog
(§8) depuis plusieurs jours.

⚠️ CHANGEMENT DE MODE DE PANNE — décision utilisateur du 24/07
Aujourd'hui, chaque table a une tranche « par défaut » (booking_default, etc.) qui rattrape les
lignes hors période. Vérifié en sandbox : une réservation d'octobre est ACCEPTÉE et rangée
dans ce fourre-tout — pas de rejet, mais dégradation silencieuse. Pire, PostgreSQL refuse
ensuite de créer la tranche d'octobre tant que ces lignes y sont :
   ERROR: updated partition constraint for default partition would be violated by some row
Réparer devient d'autant plus lourd qu'on a tardé.

Décision : SUPPRIMER les tranches par défaut. Assumé et voulu — mieux vaut une panne bruyante
qu'une dérive invisible. Conséquence à intégrer partout : sans filet, une tranche manquante
REJETTE l'écriture. L'automatisation et sa surveillance deviennent critiques pour
l'exploitation, plus un simple confort.

═══════════════════════════════════════════════════════════════════
DÉCISIONS UTILISATEUR — table par table
═══════════════════════════════════════════════════════════════════
| Table                | Avance   | Rétention                                        |
|----------------------|----------|--------------------------------------------------|
| booking              | 3 mois   | AUCUNE purge — historique commercial et fiscal   |
| core_session         | 3 mois   | 3 mois puis suppression                          |
| core_auth_attempt    | 3 mois   | 3 mois puis suppression                          |
| provider_call_log    | 3 mois   | AUCUNE purge automatique — gérée côté applicatif |

Règle métier provider_call_log, à documenter explicitement dans le schéma ET le backlog :
  « Un journal d'appel rattaché à une réservation n'est JAMAIS supprimé. Seuls les appels
    n'ayant pas abouti sont purgés après un mois. Purge assurée par l'application, pas par
    pg_partman. DÉCISION RÉVISABLE — l'utilisateur a explicitement demandé qu'elle soit
    tracée comme telle pour qu'on puisse y revenir. »

A. SUPPRIMER LES TRANCHES PAR DÉFAUT (4 lignes reference/)
B. TRANCHES BOOTSTRAP 3 mois + pg_partman étape OBLIGATOIRE déploiement
C. INSTALLER ET CONFIGURER pg_partman
D. SURVEILLANCE — requête + seuil < 2 mois (pas de système d'alerte)

VÉRIFICATION : chaîne 16/16 ; 0 *_default ; test rejet ; rétention part_config brut ;
qualité PHP. Clôture §8. Journal. Commit + PUSH OBLIGATOIRE.
```

## Décisions prises

- Tableau avance / rétention des 4 tables (utilisateur, 24/07)
- Suppression des tranches par défaut, panne bruyante assumée (utilisateur, 24/07)
- Purge `provider_call_log` côté applicatif, marquée RÉVISABLE (utilisateur, 24/07)
- pg_partman intégré à la procédure de déploiement (architecte DB)
- Nommage bootstrap aligné convention pg_partman `_pYYYYMMDD` (architecte DB)
- Maintenance via cron conteneur (pas BGW : LLVM bitcode Alpine incompatible ;
  paquet apk partman = PG18, build source 5.2.4 obligatoire) (architecte DB)

---

# Journal — 2026-07-24 — pg_partman partitionnement (§8)

## Livrables

- reference/ : retrait des 4 `*_default`, bootstrap 3 mois, commentaires anti-DEFAULT,
  noms `_pYYYYMMDD`
- `docker/postgres/` : Dockerfile (PG16 + pg_partman 5.2.4), cron maintenance,
  `sql/drop_default_partitions.sql`, `sql/pg_partman_setup.sql`
- `docs/decisions/2026-07-24-pg-partman-deploiement.md`
- `docs/ops/pg-partman-surveillance.md` (seuil alerte : `< 2` mois d'avance)
- Migration `Version20260724170000` (runtime `booking`)
- Backlog §8 clôturé (volet pg_partman)

## Version installée

```text
pg_partman | 5.2.4
PostgreSQL 16.14 (Alpine) — compatible partition native
```

## Vérification 1 — chaîne 16/16

```text
=== STEP 1/16 … 16/16 === OK
 tables 
--------
    299
```

## Vérification 2 — aucune tranche DEFAULT

```text
SELECT c.relname FROM pg_class c JOIN pg_inherits i ON i.inhrelid=c.oid
WHERE c.relname LIKE '%_default%';
 → 0 ligne (runtime + chain_verify)
```

## Vérification 3 — test de rejet (brut)

```text
ERROR:  no partition of relation "booking" found for row
DETAIL:  Partition key of the failing row contains (booking_date) = (2027-10-15).

ERROR:  no partition of relation "provider_call_log" found for row
DETAIL:  Partition key of the failing row contains (created_at) = (2027-10-15 12:00:00+00).
```

## Vérification 4 — part_config (rétention, brut)

```text
       parent_table       | premake | retention | retention_keep_table | infinite_time_partitions 
--------------------------+---------+-----------+----------------------+--------------------------
 public.booking           |       3 |           | t                    | t
 public.core_auth_attempt |       3 | 3 months  | f                    | f
 public.core_session      |       3 | 3 months  | f                    | f
 public.provider_call_log |       3 |           | t                    | t
```

`booking` et `provider_call_log` : `retention` NULL — aucune purge automatique.
`core_session` / `core_auth_attempt` : `3 months`, `retention_keep_table = f` (DROP).

## Vérification 5 — avance ≥ 3 mois

Après `run_maintenance`, chaque parent a une dernière tranche au moins
`…_p20261001` (octobre) depuis juillet 2026 → ≥ 3 mois d'avance.

## Qualité

```text
phpstan : OK (0 errors)
deptrac : 0 violations
phpunit : OK (397 tests, 2 notices préexistants)
php-cs-fixer : migration §8 propre ; dette préexistante hors périmètre sur le reste
```
