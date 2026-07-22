# Journal — 2026-07-22 — Clôture crédit instrument + lettrage

## Clôture

Vague validée : crédit depuis instrument Active (`amountMinor` négatif,
`reglement_client` / `reglement_fournisseur`), lettrage soft-unmatchable,
même livre applicatif, plafonds crédit (formule schéma) **et** débit
(inférence « partiel des deux côtés » — prouvée par tests
`exceeds_debit` + partiels = capacité débit). Aucune écriture applicative
sur `reglement_balance`. Pas d'orchestration auto-matching.

Journal de vague :
`docs/journal/2026-07-22-reglement-credit-matching.md`

## Qualité finale

- phpstan OK
- deptrac 0
- phpunit **352 tests / 2176 assertions** (2 notices préexistants)
- phpcpd : clones acceptés (cf. todo) — aucun nouveau Matching/Credit

## Suite

Orchestration auto-matching — ou lecture `reglement_balance` — ou HTTP
Règlements.
