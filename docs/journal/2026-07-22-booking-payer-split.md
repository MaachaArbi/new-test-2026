## Reprise à froid

Journal — 2026-07-22 — BookingPayerSplit.
`booking_payer_split` : répartition historisée du montant à payer entre plusieurs payeurs (ex. amicale / employé). Pattern assign/revoke comme `BookingSettlement`.
`booking_payer_split` : répartition historisée du montant à payer entre
plusieurs payeurs (ex. amicale / employé). Pattern assign/revoke comme

## Origine

```
# TASK — Module Booking : BookingPayerSplit (Domain + Application + Infrastructure)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, table booking_payer_split
2. BookingSettlement.php (pattern historisé déjà validé — assign/revoke,
   valid_from/valid_to, contrainte "un seul actif" via index partiel)
3. Booking.php (totalVenteAmount déjà exposé en lecture, on ne le mute
   JAMAIS ici — seule la vérification de non-dépassement en a besoin)

## Règle métier confirmée (ne pas inventer autre chose)
Le montant total des splits actifs d'un booking ne doit JAMAIS dépasser
booking.total_vente_amount. PAS d'exigence d'égalité stricte — une
répartition peut être temporairement incomplète (ex: 100% amicale au
départ, ajustée à 300/700 employé/amicale plus tard). Seule contrainte à
l'assignation d'une NOUVELLE ligne : somme(actifs existants) + nouveau
montant <= total_vente_amount. Si dépassement → rejet AVANT écriture.

## Domain — BookingPayerSplit (agrégat historisé, pattern assign/revoke)
src/Modules/Booking/Domain/Entity/BookingPayerSplit.php
- id (IDENTITY), bookingId, payerAccountId, amount (Money, devise vente
  du booking), validFrom, validTo, createdBy
- assign(...) : factory, validFrom=now, validTo=null. PAS de vérification
  de plafond ICI (le Domain ne connaît pas la somme des autres lignes ni
  le total du booking — cette vérification est une orchestration
  Application, pas une règle Domain pure)
- revoke(): void — rejette une double révocation (cohérent avec
  BookingSettlement/PartyAccountRoleAssignment)
- isActive(): bool

## Repository
BookingPayerSplitRepositoryInterface :
- findById, findByBookingId(bookingId, activeOnly=true par défaut)
- sumActiveAmountForBooking(bookingId): int (DBAL direct — SUM des
  montants actifs en centimes, cohérent ADR-003, PAS de chargement de
  collection pour sommer en PHP)
- assign/revoke via UnitOfWork

## Application — la vraie logique de cette vague
AssignBookingPayerSplit/{Command,Handler} :
- Charge booking->totalVenteAmount() (lecture simple via BookingRepository
  ->findById — usage légitime, PAS de mutation du booking)
- Calcule sumActiveAmountForBooking(bookingId) + nouveau montant
- Si la somme dépasserait le total → lève une nouvelle exception
  BookingPayerSplitExceedsTotalException (contexte : bookingId,
  montant déjà réparti, nouveau montant demandé, total autorisé)
- Sinon, assign() + persist() + commit() unique (UnitOfWork)
- Vérifier aussi la devise : le montant fourni doit être dans la même
  devise que total_vente_amount, sinon exception dédiée (cohérent avec
  assertMoneyCurrency déjà vu partout)

RevokeBookingPayerSplit/{Command,Handler} : find → revoke() → commit,
même pattern que RevokeBookingSettlement.

## Tests (PostgreSQL réel)
- Assignation simple → round-trip complet
- Deux assignations successives dont la somme égale exactement le total
  → toutes deux acceptées (cas limite : somme = total, pas de marge
  d'erreur, doit passer)
- Assignation qui ferait dépasser le total (même d'un centime) → rejetée
  avant SQL, aucune ligne créée
- Après un revoke, le montant libéré peut être réassigné (le plafond se
  recalcule sur les lignes ACTIVES uniquement, pas l'historique complet)
- Devise différente du booking → rejetée
- Vérifier explicitement qu'AUCUNE mutation du Booking ne se produit
  (même test de non-régression que sur BookingSettlement : valeurs
  avant/après identiques)
- Test structurel (réflexion, comme sur BookingSettlement) : le Handler
  d'assignation a bien une dépendance en LECTURE à BookingRepository
  (légitime ici, contrairement à Settlement) mais aucune méthode de
  mutation du Booking n'est jamais appelée dans son code

## Documentation
- docs/journal/2026-07-2X-booking-payer-split.md — expliquer
  explicitement la règle de plafond (jamais égalité stricte, confirmé
  utilisateur), et la distinction avec BookingSettlement (celui-ci lit le
  total sans jamais le muter, l'autre ne le touche même pas en lecture)
- docs/STATUS.md : "Booking : pan financier historisé complet (charge,
  settlement, payer_split). Reste : payment (différé, provisoire), HTTP
  charges/settlement/payer_split."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — BookingPayerSplit

## Contexte

`booking_payer_split` : répartition historisée du montant à payer entre
plusieurs payeurs (ex. amicale / employé). Pattern assign/revoke comme
`BookingSettlement`.

## Règle de plafond (confirmée utilisateur)

À l’assignation d’une **nouvelle** ligne active :

`SUM(amount des actifs) + nouveau montant <= booking.total_vente_amount`

- **Pas** d’exigence d’égalité stricte — une répartition peut rester
  incomplète temporairement.
- Égalité exacte (**somme = total**) : **acceptée** (cas limite testé).
- Dépassement d’**un centime** : rejet Application avant SQL
  (`BookingPayerSplitExceedsTotalException`).

Note : le COMMENT SQL de référence évoque encore une égalité SUM = total ;
la règle métier **confirmée** pour OsTravel est le plafond (≤), pas
l’égalité. Documenté ici pour éviter une régression de lecture du schéma.

## Distinction Settlement vs PayerSplit

| | Settlement | PayerSplit |
|---|---|---|
| Lit `Booking.totalVenteAmount` | Non | Oui (lecture seule) |
| Mute le Booking | Non | Non |
| Plafond Application | Non | Oui (SUM actifs) |

## Devise

Colonne `currency_code` absente du schéma → montant en centimes devise
vente du booking ; `hydrateCurrency()` en Repository (ADR-003 DBAL sur
`vente_currency_code`). Mismatch → `BookingPayerSplitCurrencyMismatchException`.

## Index unique

`uq_booking_payer_split_active (booking_id, payer_account_id)` →
`hasActivePayerSplit` DBAL + `BookingPayerSplitAlreadyActiveException`
(même discipline que settlement / rôles Party).

### Test ajouté a posteriori (distinct du plafond)

`test_second_active_split_same_payer_is_rejected_before_sql` :

- Même `payer_account_id`, 2ᵉ assign actif sur le même booking.
- Montants choisis pour que le **plafond soit OK** (10k+10k ≤ 100k) —
  seul le doublon actif bloque (`already_active`), pas `exceeds_total`.
- Justifie le garde-fou Application miroir de l’index unique, indépendant
  de la règle de somme vs `total_vente_amount`.

## Nettoyage getters

Getters simplifiés (`return $this->x`) dans `BookingPayerSplit` uniquement.
Le trait `HistorizedBookingChildAccessors` a été introduit hors périmètre
puis **annulé** — cf. `2026-07-22-revert-historized-accessors-trait.md`.
Clone phpcpd Settlement ↔ PayerSplit **accepté** (todo.md).

## Qualité (clôture)

phpunit **304 tests / 1800 assertions** (dont le test hasActive ci-dessus) ;
phpstan OK ; deptrac 0 ; phpcpd : clone accepté documenté (voir todo).
