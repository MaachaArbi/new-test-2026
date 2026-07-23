## Reprise à froid

Journal — 2026-07-22 — Clôture grand livre Règlements (obligation + transfert).
Vague validée : Domain append-only, obligation depuis Booking (INSERT
Domain-contrôlé), transfert via `reglement_post_transfer()`, trigger
prouvé empiriquement, exceptions Domain alignées, isolation tests

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Clôture grand livre Règlements (obligation + transfert)

## Clôture

Vague validée : Domain append-only, obligation depuis Booking (INSERT
Domain-contrôlé), transfert via `reglement_post_transfer()`, trigger
prouvé empiriquement, exceptions Domain alignées, isolation tests
documentée (autocommit, pas DAMA).

Journal de vague :
`docs/journal/2026-07-22-reglement-ledger-obligation-transfer.md`

## Qualité finale

- phpstan OK
- deptrac 0
- phpunit **339 tests / 2082 assertions** (2 notices préexistants)
- phpcpd : clones acceptés (cf. todo)

## Suite

Credit instrument / matching — ou HTTP Règlements.
