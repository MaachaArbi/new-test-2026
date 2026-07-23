## Reprise à froid

Journal — 2026-07-22 — Règlements : grand livre (obligation + transfert).
Vague isolée du vrai risque du module : `reglement_ledger_entry` append-only (trigger), snapshot `reglement_balance` (trigger), et `reglement_post_transfer()` (atomicité multi-jambes).
- `ReglementLedgerEntry::post()` factory unique. - Valide `amountMinor !== 0` et **au moins une origine** (miroir `chk_entry_has_origin`) **avant** SQL. - **Aucune** méthode de mutation publique après création — une…

## Origine

```
# TASK — Module Règlements : ReglementLedgerEntry (obligation + transfert)

## Lecture obligatoire
1. reference/schemas/schema-reglements-v1.sql (à jour, re-synchronisé) :
   reglement_ledger_entry (trigger append-only), reglement_entry_type,
   reglement_post_transfer() (signature corrigée), reglement_balance
2. reference/conceptual-models/modele-conceptuel-reglements.md
3. Booking.php + BookingRepositoryInterface (lecture de booking_payer_split
   déjà existante) — Règlements LIT Booking, n'écrit JAMAIS dedans (rappel
   explicite du modèle conceptuel : "← Booking, lecture uniquement")

## Contrainte structurelle absolue
ReglementLedgerEntry n'a JAMAIS de méthode de mutation après création —
le trigger PostgreSQL rejette physiquement tout UPDATE/DELETE, mais le
Domain doit refléter cette même discipline : aucune méthode publique ne
doit permettre de modifier une instance déjà créée. Une correction est
TOUJOURS une nouvelle écriture (contre-passation via reverses_entry_id),
jamais une modification.

## 1. Domain — ReglementLedgerEntry (Domain, création uniquement)
src/Modules/Reglements/Domain/Entity/ReglementLedgerEntry.php
- id, publicId, partyAccountId, partyRole (InstrumentPartyRole, déjà
  existant, réutiliser), currencyCode, entryTypeId, amountMinor (int,
  signé, CHECK <> 0 — valider en Domain), effectiveDate
  (DateTimeImmutable), bookingId/instrumentId/invoiceId/creditNoteId/
  transferId/reversesEntryId (tous ?int), memo (?string), createdBy (?int)
- post(...) : factory UNIQUE, valide amountMinor !== 0, valide qu'AU
  MOINS une origine est renseignée (miroir du CHECK
  chk_entry_has_origin — le Domain doit refuser AVANT SQL, pas compter
  sur le trigger comme seule protection)
- AUCUNE autre méthode publique que les getters — pas de revoke(), pas de
  cancel(), rien. Une correction future créera une NOUVELLE instance via
  post() avec reversesEntryId renseigné, jamais une mutation de
  l'existante.

## 2. Repository — écriture SEULEMENT via INSERT, jamais via UnitOfWork générique
ReglementLedgerEntryRepositoryInterface :
- findById, findByBookingId(array), append(ReglementLedgerEntry): void
  (nommage volontairement différent de "save" — souligne qu'il n'y a
  jamais de comportement update possible sur ce Repository)
- sumActiveByBook(partyAccountId, partyRole, currencyCode): int (DBAL,
  ADR-003 — équivalent du calcul de solde à froid, PAS le snapshot
  reglement_balance qu'on ne touche jamais nous-mêmes, il est maintenu
  par trigger)

## 3. Application — Obligation (INSERT Domain-contrôlé)
PostReglementObligationFromBooking/{Command,Handler} :
- Command : bookingId (int)
- Handler : lit booking_payer_split actifs (nouveau
  BookingPayerSplitRepositoryInterface::findByBookingId déjà existant),
  pour CHAQUE split actif : résout entryTypeId pour 'obligation_vente'
  (lecture référentiel), construit un ReglementLedgerEntry::post() avec
  amountMinor = +montant du split (signe cohérent avec normal_sign=+1),
  currencyCode = devise vente du booking, effectiveDate = aujourd'hui,
  bookingId renseigné
- TOUTES les écritures générées pour un même booking sont persistées
  (append) dans le MÊME commit() UnitOfWork — cohérent avec la discipline
  déjà établie, une opération métier logique = un seul commit

## 4. Application — Transfert (appel fonction SQL, pas Doctrine)
PostReglementTransfer/{Command,Handler} :
- Command : sourceAccountId, sourceRole, targetAccountId, targetRole,
  currencyCode, amountMinor, effectiveDate, reason, createdBy
- Handler : appelle reglement_post_transfer() via DBAL direct
  (Connection::executeQuery ou fetchOne selon ce que retourne la fonction
  — RETURNS BIGINT, l'id du transfert créé), PAS Doctrine ORM ici (ni
  UnitOfWork ni persist — c'est un appel de fonction SQL qui gère sa
  propre transaction en interne)
- Vérifier explicitement dans un test que les 2 jambes ET la ligne
  reglement_transfer sont bien créées atomiquement (round-trip complet)

## Tests (PostgreSQL réel)
- post() rejette amountMinor=0 avant SQL
- post() rejette une écriture sans AUCUNE origine avant SQL
- Obligation : booking avec 2 payer_split actifs → 2 écritures créées,
  montants corrects, même bookingId, currencyCode cohérent
- Obligation : vérifier via sumActiveByBook() que le solde calculé à
  froid correspond bien à la somme attendue
- Transfert : appel réel de la fonction, vérifier les 2 jambes + la ligne
  transfer, vérifier via une requête SQL brute (pas Doctrine) que les 3
  lignes existent bien avec les bons montants/signes
- Test négatif du trigger (comme pour le garde-fou PHPStan tout à
  l'heure) : tenter un UPDATE SQL direct sur une ligne reglement_ledger_entry
  déjà créée, vérifier que PostgreSQL lève bien l'exception du trigger —
  preuve empirique que le append-only fonctionne réellement, pas supposé

## Documentation
- docs/journal/2026-07-2X-reglement-ledger-obligation-transfer.md —
  documenter explicitement le pattern mixte (fonction SQL pour transfert,
  INSERT Domain pour obligation) et pourquoi, citer la réponse du DB
  architect
- docs/STATUS.md : "Règlements : grand livre (obligation depuis Booking +
  transfert via fonction SQL) fait. Reste : credit instrument (dépend du
  lettrage, vague séparée), matching, HTTP."
- docs/backlog/todo.md

Si un cas imprévu apparaît (ex: la fonction reglement_post_transfer ne se
comporte pas comme documenté), ARRÊTE-TOI et signale précisément plutôt
que de contourner.

Relance phpstan/deptrac/phpcpd/phpunit. Vu le volume, remonte en plusieurs
messages — je vérifierai chaque fichier comme d'habitude, en particulier
le test négatif du trigger qui est la preuve la plus importante de cette
vague.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
