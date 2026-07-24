## Reprise à froid

§67 — cycle de vie brouillon/validation des bordereaux `cash_deposit` et
`cash_external_transmission`. 7 décisions utilisateur. Tables absentes en
runtime → reference/ + resserrement index mouvement. Tests fonctionnels sur
chaîne verify.

## Origine

```
TASK — §67 : cycle de vie brouillon/validation des bordereaux
(dépôt banque + transmission externe)

Décisions 1–7 (utilisateur, 24/07) : brouillon sans effet comptable ; clôture
refusée si brouillon ; un brouillon/session ; suppression brouillon / annulation
validé ; pas de réouverture ; confirmation = non-retour ; fonds à la validation.

Structure : status_code + session_id + lifecycle CHECK + index unique brouillon ;
ts nullable ; lignes instrument_id + amount_minor ; movement_id nullable ;
item transmission DEFAULT draft ; cash_close_session + validate/cancel.

Tests fonctionnels 1–9 obligatoires. Journal + commit + PUSH.
```

## Décisions prises

- Les 7 décisions (utilisateur, 24/07)
- Contrôle dans `cash_close_session` plutôt qu'un nouveau trigger (architecte DB, validé utilisateur)
- Pas de transition réouverture (architecte DB, validé utilisateur)

---

# Journal — 2026-07-24 — cycle de vie bordereaux (§67)

## Écarts

1. `cash_deposit` / `cash_external_transmission` **absentes** en runtime → pas de migration structurelle bordereaux ; `cash_close_session` runtime **non** mis à jour (dépendrait de tables absentes).
2. Index `uq_cash_movement_instrument_per_session` resserré (encaissements `amount_minor > 0` hors contre-passation) — nécessaire pour sorties/contre-passations du même instrument ; migration `Version20260724160000`. Aligné sur l'intention §63.
3. Compte de tables inchangé : **299**.

## Vérification 1 — chaîne

```text
16/16 OK
 tables = 299
```

## Tests fonctionnels (brut)

```text
T1 baseline: balance 150000, movements 2
T2 draft: movements 2, balance 150000, status draft, deposited_at NULL, movement_id NULL
T3 close+draft: ERROR Impossible de clôturer la session 1 : bordereau de dépôt 1 encore en brouillon…
T4 delete draft: remaining 0
T5 2e brouillon: ERROR duplicate key uq_cash_deposit_one_draft_per_session
T6 validate: status validated, movement_id set, balance 50000, bank_deposit_out -100000
T7 fonds insuffisants: ERROR … instrument 2 disponible 50000, demandé 60000
T8 cancel: status cancelled, balance 0 → 50000
T9 cancel confirmé: ERROR Dépôt … déjà confirmé en banque … annulation interdite
T4b close sans brouillon: status closed
```
