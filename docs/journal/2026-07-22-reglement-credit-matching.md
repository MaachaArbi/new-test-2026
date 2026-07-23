## Reprise à froid

Journal — 2026-07-22 — Règlements : crédit instrument + lettrage.
Briques primitives uniquement — **pas** d'orchestration auto-matching (CB → crédit + lettrage en une action) : vague future qui composera ces handlers.
Briques primitives uniquement — **pas** d'orchestration auto-matching
(CB → crédit + lettrage en une action) : vague future qui composera

## Origine

```
# TASK — Module Règlements : crédit instrument + lettrage (matching)

## Lecture obligatoire
1. reference/schemas/schema-reglements-v1.sql : reglement_matching
   (contrainte "même livre" applicative, formule du restant à allouer),
   reglement_entry_type (reglement_client/reglement_fournisseur, signe -1)
2. ReglementLedgerEntry.php + ReglementInstrument.php (déjà validés)

## Portée — 3 opérations primitives, PAS d'auto-matching orchestré
Cette vague pose les briques ; l'orchestration "paiement CB → crédit +
lettrage automatique en une seule action" reste une vague future qui
composera ces primitives, pas construite ici.

## 1. Crédit instrument (Application) — poste une écriture de crédit
PostReglementCreditFromInstrument/{Command,Handler} :
- Command : instrumentId
- Handler : charge l'instrument (doit être status=Active — sinon
  exception dédiée, un instrument returned/cancelled ne peut pas générer
  de crédit), résout entry_type selon partyRole de l'instrument
  ('reglement_client' si Client, 'reglement_fournisseur' si Fournisseur),
  poste un ReglementLedgerEntry::post() avec amountMinor NÉGATIF (signe
  -1, cohérent avec normal_sign), instrumentId renseigné comme origine,
  currencyCode = celle de l'instrument, effectiveDate = aujourd'hui
- Un seul commit() (UnitOfWork), cohérent avec la discipline déjà établie

## 2. Créer un lettrage (Domain + Application)
src/Modules/Reglements/Domain/Entity/ReglementMatching.php
- id, publicId, debitEntryId, creditEntryId, matchedAmountMinor (> 0),
  isAutomatic, matchGroup (?string), matchedAt, matchedBy (?int),
  unmatchedAt (?DateTimeImmutable), unmatchedBy (?int)
- match(...) : factory, CHECK debitEntryId <> creditEntryId (miroir
  chk_matching_distinct, Domain AVANT SQL)
- unmatch(?int $unmatchedBy): void — soft, rejette un double unmatch
  (cohérent avec le pattern revoke déjà établi partout)
- isActive(): bool (unmatchedAt === null)

ReglementMatchingRepositoryInterface :
- sumActiveMatchedForCreditEntry(creditEntryId): int (DBAL)
- sumActiveMatchedForDebitEntry(debitEntryId): int (DBAL)
- match/unmatch via UnitOfWork

CreateReglementMatching/{Command,Handler} :
- Charge les deux ReglementLedgerEntry (debit, credit)
- Vérifie même livre : partyAccountId + partyRole + currencyCode
  identiques sur les deux écritures — sinon exception dédiée (règle
  explicitement "applicative" selon le schéma, pas une FK)
- Vérifie plafond côté crédit : sumActiveMatchedForCreditEntry(credit) +
  nouveauMontant <= |amountMinor| de l'écriture crédit (valeur absolue,
  le montant en base est négatif) — sinon exception dédiée
- Vérifie plafond côté débit : même logique symétrique côté débit — NOTE
  DANS LE JOURNAL : cette règle est une INFÉRENCE depuis "partiel
  autorisé des deux côtés" (commentaire schéma), pas une formule
  explicitement donnée comme côté crédit. Si tu juges le risque
  d'invention trop grand, implémente uniquement le côté crédit
  (explicitement confirmé par le schéma) et documente le côté débit comme
  question ouverte à trancher avec l'utilisateur plutôt que d'inventer
  silencieusement — DÉCIDE et justifie ton choix dans le journal
- match() + commit() unique

UnmatchReglementMatching/{Command,Handler} : find → unmatch() → commit

## Tests (PostgreSQL réel)
- Crédit instrument : round-trip, amountMinor négatif confirmé, entry_type
  correct selon partyRole (client vs fournisseur, 2 cas testés)
- Crédit sur instrument non-Active → rejeté avant SQL
- Lettrage simple : debit+credit du même livre → OK
- Lettrage entre deux livres différents (devise ou compte différent) →
  rejeté avant SQL
- Lettrage qui dépasserait le montant de l'écriture crédit → rejeté
- Deux lettrages partiels sur la même écriture crédit dont la somme égale
  exactement le montant → tous deux acceptés (cas limite, comme
  payer_split)
- Unmatch puis nouveau lettrage sur le même couple → le plafond se
  recalcule sur les actifs uniquement
- Vérifier qu'AUCUNE mutation de reglement_balance n'est jamais tentée
  directement par notre code (lecture éventuelle possible, jamais
  d'écriture — c'est le trigger qui la maintient)

## Documentation
- docs/journal/2026-07-2X-reglement-credit-matching.md — TRANCHER et
  justifier explicitement la question du plafond côté débit (inféré ou
  différé), citer le raisonnement
- docs/STATUS.md : "Règlements : crédit instrument + lettrage (matching)
  faits. Reste : orchestration auto-matching, reglement_balance (lecture),
  HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés (texte brut collé directement dans le message, PAS de
pièce jointe — on a eu des soucis de transmission tout à l'heure) et les
résultats. Vu le volume, plusieurs messages sont attendus.
```

## Décisions prises

Décisions attribuées :
- Mandat de décision délégué par le prompt d'origine (Cursor — à valider)

---

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
