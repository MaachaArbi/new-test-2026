# Journal — 2026-07-22 — Règlements : crédit instrument + lettrage

## Contexte

Briques primitives uniquement — **pas** d'orchestration auto-matching
(CB → crédit + lettrage en une action) : vague future qui composera
ces handlers.

## Crédit instrument

`PostReglementCreditFromInstrument` : instrument **Active** obligatoire →
écriture `amountMinor` **négatif** (`reglement_client` /
`reglement_fournisseur` selon `partyRole`), origine `instrumentId`.

## Lettrage

`ReglementMatching` : soft unmatch (`unmatchedAt`), miroir
`chk_matching_distinct` en Domain. Même livre = règle **applicative**
(compte + rôle + devise).

### Décision plafond côté débit — IMPLÉMENTÉ

| Côté | Source | Décision |
|---|---|---|
| Crédit | Formule explicite schéma (COMMENT restant = \|credit\| − SUM actifs) | Implémenté |
| Débit | Inférence depuis « partiel autorisé **des deux côtés** » (COMMENT colonne `matched_amount_minor`) | **Implémenté** — symétrie |

Justification : sans plafond débit, on pourrait lettrer plus que le
montant d'une obligation alors que le schéma affirme le partiel des
deux côtés. Ce n'est pas une formule inventée hors texte : c'est
l'application symétrique de la capacité restante déjà documentée pour
le crédit. `abs(amount_minor)` des deux côtés (crédits négatifs, débits
positifs).

Si le métier veut un jour un lettrage « ouvert » côté débit sans
plafond, il faudra une décision utilisateur explicite pour lever cette
garde.

## Isolation / balance

- Aucune écriture applicative sur `reglement_balance` (trigger only).
- Tests : scan source des handlers/repos concernés.

## Hors périmètre

- Auto-matching orchestré
- Lecture métier `reglement_balance`
- HTTP

## Qualité

- phpstan : OK (0 erreur)
- deptrac : 0 violation
- phpunit : 352 tests, 2176 assertions (2 notices préexistants)
- phpcpd : clones déjà documentés (Instrument↔Ledger, referentials, Booking) — aucun nouveau clone Matching/Credit
