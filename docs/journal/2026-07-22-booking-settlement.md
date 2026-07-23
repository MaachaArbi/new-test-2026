## Reprise à froid

Journal — 2026-07-22 — BookingSettlement.
`booking_settlement` : faits de répartition par bénéficiaire (fournisseur / agence principale / distributeur). Historisé (assign / revoke). **Aucun** recalcul des totaux ou marges `Booking` (frontière nette avec…
`booking_settlement` : faits de répartition par bénéficiaire (fournisseur /
agence principale / distributeur). Historisé (assign / revoke). **Aucun**

## Origine

```
# TASK — Module Booking : BookingSettlement (Domain + Application + Infrastructure)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, table booking_settlement
   (colonnes exactes, commentaire explicite : Booking ne génère AUCUNE
   échéance, resale_price_amount est PUREMENT informatif)
2. PartyAccountRoleAssignment.php (pattern historisation déjà validé :
   assign/revoke, valid_from/valid_to, contrainte "un seul actif")
3. ExchangeRate.php (pattern string-jamais-float pour NUMERIC) — rate ici
   est NUMERIC(6,3), précision DIFFÉRENTE de ExchangeRate (14,6). Décide :
   soit un nouveau petit VO dédié (SettlementRate ou équivalent, même
   principe mais précision propre), soit une généralisation d'ExchangeRate
   avec précision paramétrable — choisis la solution la plus propre et
   documente le choix dans le journal, ne duplique pas bêtement le VO
   existant avec une regex copiée-collée sans réflexion

## Contrainte explicite — À NE PAS FAIRE
booking_settlement ne déclenche JAMAIS de recalcul sur Booking (ni
totalVenteAmount, ni marges). Contrairement à BookingCharge qui recalcule
les totaux, BookingSettlement est un fait de répartition indépendant, lu
plus tard par le futur module Cash Management. Ne PAS reproduire le
pattern recalculateTotals() ici.

## Domain
### BeneficiaryRole (enum PHP natif — CHECK SQL fixe)
src/Modules/Booking/Domain/ValueObject/BeneficiaryRole.php
enum : Fournisseur = 'fournisseur', AgencePrincipale = 'agence_principale',
Distributeur = 'distributeur'

### BookingSettlement (agrégat historisé, pattern assign/revoke)
src/Modules/Booking/Domain/Entity/BookingSettlement.php
- id (IDENTITY), bookingId, beneficiaryAccountId, beneficiaryRole
  (BeneficiaryRole), amountOwed (Money), amountSettledDirect (Money,
  défaut 0), rate (?string, VO à trancher), resalePriceAmount (?Money),
  currencyCode (string — sert de devise commune à amountOwed/
  amountSettledDirect/resalePriceAmount), validFrom (DateTimeImmutable),
  validTo (?DateTimeImmutable)
- assign(...) : factory de création, validFrom=now, validTo=null
- revoke(): void — clôture (même comportement que
  PartyAccountRoleAssignment : rejette une double révocation, cohérent
  avec la décision déjà prise sur ce pattern)
- isActive(): bool

## Repository
BookingSettlementRepositoryInterface :
- findById, findByBookingId(array, actifs uniquement par défaut ou tous —
  décide et documente), hasActiveSettlement(bookingId, beneficiaryRole,
  beneficiaryAccountId): bool (DBAL direct, cohérent ADR-003 — miroir de
  uq_booking_settlement_active), assign(via UnitOfWork), revoke(via
  UnitOfWork)

## Application
AssignBookingSettlement/{Command,Handler} :
- Vérifie hasActiveSettlement() AVANT assign() (même discipline que
  hasActiveRole) — pas de doublon actif sur le triplet
  (bookingId, beneficiaryRole, beneficiaryAccountId)
- UnitOfWork : persist() + commit() unique, pas de flush séparé

RevokeBookingSettlement/{Command,Handler} :
- findById → Domain revoke() → persist (précondition UnitOfWork déjà
  documentée ailleurs) → commit unique

## Tests (PostgreSQL réel)
- Assignation simple → round-trip complet, tous les champs
- Doublon actif (même triplet) → rejeté avant SQL
- Deux beneficiary_role différents pour le même booking+compte →
  coexistent (pas un doublon)
- Revoke → valid_to persisté, réassignation possible après
- resalePriceAmount : vérifier qu'aucun code ne l'agrège nulle part
  (absence de règle de recalcul — test négatif explicite si pertinent,
  sinon documenter que ce n'est structurellement pas possible vu
  qu'aucune méthode ne le permet)
- Vérifier explicitement qu'AUCUN test ne s'attend à une mutation de
  Booking suite à une assignation de settlement (non-régression du
  principe "pas de recalcul ici")

## Documentation
- docs/journal/2026-07-2X-booking-settlement.md — expliquer le choix VO
  pour rate, et documenter explicitement l'absence de recalcul (frontière
  avec BookingCharge)
- docs/STATUS.md
- docs/backlog/todo.md : reste payer_split, payment (différé, provisoire),
  HTTP settlement

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — BookingSettlement

## Contexte

`booking_settlement` : faits de répartition par bénéficiaire (fournisseur /
agence principale / distributeur). Historisé (assign / revoke). **Aucun**
recalcul des totaux ou marges `Booking` (frontière nette avec
`BookingCharge` + `recalculateTotals()`).

`resale_price_amount` : purement informatif (revente hors périmètre
financier de ce booking) — aucune agrégation Domain/Application.

## Choix VO pour `rate` (NUMERIC(6,3))

**Pas** de duplication de la regex `ExchangeRate` (14,6).

- Nouveau helper Shared `NumericDecimal::isValidPositive($value, $precision, $scale)`
  — règle générique NUMERIC(p,s), string jamais float.
- `ExchangeRate` refactoré pour déléguer à `NumericDecimal(14, 6)`.
- Nouveau VO Booking `SettlementRate` via `NumericDecimal(6, 3)` + type
  Doctrine `settlement_rate`.

Sémantique distincte (taux de change vs taux commission settlement) → deux
VO ; validation partagée → pas de copier-coller de pattern.

## Repository

- `findByBookingId(int $bookingId, bool $activeOnly = true)` — **actifs
  uniquement par défaut** (`valid_to IS NULL`) ; `activeOnly: false` pour
  l’historique.
- `hasActiveSettlement(bookingId, role, accountId)` — DBAL `SELECT 1`
  (ADR-003), miroir `uq_booking_settlement_active`.
- `assign` / `revoke` via `UnitOfWork` (persist ; commit = Handler).

## Application

- `AssignBookingSettlementHandler` : `hasActiveSettlement` avant assign,
  un seul `commit()`. **N’injecte pas** `BookingRepository`.
- `RevokeBookingSettlementHandler` : find → Domain `revoke()` → commit.

## Tests

Integration PostgreSQL : round-trip, doublon actif, rôles distincts
coexistants, revoke + réassign, non-mutation totaux Booking, absence de
dépendance BookingRepository, resale non agrégé vers booking.

## Qualité

Voir sortie des 4 outils en fin de vague.
