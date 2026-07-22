# Journal — 2026-07-22 — Règlements : grand livre (obligation + transfert)

## Contexte

Vague isolée du vrai risque du module : `reglement_ledger_entry`
append-only (trigger), snapshot `reglement_balance` (trigger), et
`reglement_post_transfer()` (atomicité multi-jambes).

## Pattern mixte d'écriture — obligation vs transfert

| Chemin | Mécanisme | Pourquoi |
|---|---|---|
| Obligation depuis Booking | `ReglementLedgerEntry::post()` + `append()` + `commit()` UnitOfWork | Pas encore de `reglement_post_obligation()` SQL (signature liée à l'API applicative — notes d'implémentation du schéma). Le commentaire table autorise explicitement un **INSERT Domain-contrôlé** tant que la fonction dédiée n'existe pas, à condition de respecter signe / origines / transaction. |
| Transfert | `SELECT reglement_post_transfer(...)` via DBAL | Fonction SQL déjà figée : crée `reglement_transfer` + 2 jambes atomiquement. Impossible d'obtenir un demi-transfert. **Pas** Doctrine ORM / UnitOfWork. |

Citation schéma (`reglement_ledger_entry` COMMENT, re-synchronisé) :

> « Tant qu'une fonction dédiée n'existe pas, un INSERT Domain-contrôlé
> respectant les mêmes invariants (transaction, signe, cohérence des
> jambes) est acceptable — la règle interdit le contournement de ces
> invariants, pas l'absence de fonction SQL. »

Réponse / cadrage DB architect (même ligne dans `NOTES D'IMPLÉMENTATION`
§3 du schéma) : les fonctions `post_obligation` / `post_credit` /
`post_reversal` suivront le patron de `reglement_post_transfer` une fois
l'API Symfony stabilisée ; en attendant, INSERT Domain-contrôlé OK.

## Domain

- `ReglementLedgerEntry::post()` factory unique.
- Valide `amountMinor !== 0` et **au moins une origine** (miroir
  `chk_entry_has_origin`) **avant** SQL.
- **Aucune** méthode de mutation publique après création — une
  correction future = nouvelle instance avec `reversesEntryId`.

## Repository

- `append()` (pas `save`) : `UnitOfWork::persist` — sémantique INSERT
  only ; commit = Handler.
- `sumActiveByBook()` : DBAL `SUM(amount_minor)` — solde à froid, **pas**
  lecture de `reglement_balance` (maintenu par trigger, jamais écrit
  applicativement).

## Application

- `PostReglementObligationFromBooking` : lit `booking_payer_split`
  actifs + devise vente Booking (**lecture seule**, jamais d'écriture
  Booking) → une écriture `obligation_vente` (+montant) par split → un
  seul `commit()`.
- `PostReglementTransfer` : DBAL → `reglement_post_transfer(…,
  p_currency VARCHAR(3), …)` (signature corrigée).

## Preuve append-only

Test d'intégration : INSERT d'une ligne puis `UPDATE … SET memo` brut →
exception trigger contenant `append-only` ; memo inchangé en base.

### Isolation des tests d'intégration — autocommit, PAS DAMA

Les tests d'intégration Règlements (et le reste du projet) tournent
contre **PostgreSQL réel** (`DATABASE_URL` dans `phpunit.dist.xml`) en
**autocommit simple**.

- **Pas** de `dama/doctrine-test-bundle`
- **Pas** de transaction enveloppante par test / rollback automatique
  en fin de méthode
- Chaque `Connection::executeStatement` / `fetchOne` hors
  `UnitOfWork::commit()` est une statement autonome

Conséquence pour le test négatif du trigger
(`test_ledger_append_only_trigger_rejects_update`) : l'`UPDATE` échoué
n'ouvre **pas** une transaction applicative. PostgreSQL annule
uniquement cette statement ; la connexion reste utilisable. D'où le
`SELECT memo` immédiat après le `catch` qui réussit **sans**
`ROLLBACK` explicite et **sans** erreur « current transaction is
aborted ».

Si un jour on introduisait DAMA (ou un `beginTransaction` manuel
autour du test), un statement en échec laisserait la transaction
aborted : il faudrait alors `rollBack()` / `close()` avant tout
SELECT suivant. Ne pas « redécouvrir » ce piège — noter le mode
d'isolation avant d'écrire un test trigger similaire sur une autre
table.

## Hors périmètre

- Credit instrument / lettrage (`reglement_matching`)
- `reglement_post_obligation` SQL dédié
- HTTP

## Qualité

- phpstan OK · deptrac 0 · phpunit **339 / 2082** (2 notices)
- phpcpd : clone getters Instrument↔LedgerEntry **accepté** (cf. todo)
