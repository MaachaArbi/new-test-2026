# Journal — 2026-07-22 — Règlements : lecture reglement_balance

## Contexte

Snapshot `reglement_balance` maintenu exclusivement par trigger
`AFTER INSERT` sur `reglement_ledger_entry`. Lecture métier DBAL
(ADR-003) — **pas** d'agrégat Domain (lecture seule, pas de cycle de
vie).

## Implémentation

`ReglementBalanceRepositoryInterface` :
- `findBalance(partyAccountId, partyRole, currencyCode)` → snapshot
  ou null
- `findAllBalancesForParty(partyAccountId)` → tous les livres du compte

`DoctrineReglementBalanceRepository` : `Connection` uniquement, SELECT
purs — aucune écriture (test structurel).

## Preuve de cohérence bout-en-bout

Scénario unique (obligation → crédits) :

| Étape | Action | balance_minor attendu | entry_count |
|---|---|---|---|
| 1 | Obligation +100_000 | — | — |
| 2 | Crédit instrument −60_000 | **40_000** | 2 |
| 3 | Crédit instrument −40_000 | **0** | 3 |
| 4 | `SUM(amount_minor)` froid sur le livre | **0** (= snapshot) | — |

Résultat observé : **OK** — trigger et calcul à froid convergent
(`balance_minor === SUM(amount_minor)` = 0 après le cycle complet).

## Hors périmètre

- Orchestration auto-matching
- HTTP
- Toute écriture applicative sur `reglement_balance`

## Qualité

- phpstan : OK (0 erreur)
- deptrac : 0 violation
- phpunit : 354 tests, 2201 assertions (2 notices préexistants)
- phpcpd : clones déjà documentés (todo) — aucun nouveau clone Balance
