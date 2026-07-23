## Reprise à froid

Journal — 2026-07-22 — Règlements : lecture reglement_balance.
Snapshot `reglement_balance` maintenu exclusivement par trigger `AFTER INSERT` sur `reglement_ledger_entry`. Lecture métier DBAL (ADR-003) — **pas** d'agrégat Domain (lecture seule, pas de cycle de vie).
Snapshot `reglement_balance` maintenu exclusivement par trigger
`AFTER INSERT` sur `reglement_ledger_entry`. Lecture métier DBAL

## Origine

```
# TASK — Module Règlements : lecture de reglement_balance (DBAL, jamais d'écriture)

## Lecture obligatoire
1. reference/schemas/schema-reglements-v1.sql, table reglement_balance
   (maintenue par trigger AFTER INSERT — RAPPEL : notre code ne doit
   JAMAIS y écrire, uniquement lire)
2. Tous les Handlers Règlements déjà validés (obligation, transfert,
   crédit) — c'est leur cumul qui alimente indirectement ce solde

## Portée
Lecture seule. Aucun Domain à créer (pas d'agrégat pour un simple
snapshot lu, cohérent avec le principe déjà appliqué ailleurs : une
lecture n'a pas besoin d'un agrégat Domain complet).

## Repository
ReglementBalanceRepositoryInterface (Domain) :
- findBalance(partyAccountId, partyRole, currencyCode): ?array
  {balanceMinor: int, entryCount: int, lastEntryId: ?int, updatedAt:
  DateTimeImmutable} — DBAL direct (ADR-003), lecture pure
- findAllBalancesForParty(partyAccountId): array (tous les livres d'un
  compte, toutes devises/rôles confondus)

Implémentation Doctrine : Connection uniquement, aucun Doctrine ORM,
aucune tentative d'INSERT/UPDATE sur cette table nulle part dans le
Repository (vérifier explicitement, comme le test structurel déjà fait
sur les autres Handlers de cette vague).

## Test de cohérence de bout en bout (le vrai objectif de cette vague)
Test d'intégration qui, dans un seul scénario, enchaîne :
1. Poste une obligation (via PostReglementObligationFromBooking ou
   directement via ReglementLedgerEntry::post() + append si plus simple)
   de +100_000 pour un compte
2. Poste un crédit (via un instrument) de -60_000 pour le même compte
3. Vérifie via findBalance() que balance_minor = 40_000 (100_000 - 60_000)
4. Poste un second crédit de -40_000
5. Vérifie que balance_minor = 0
6. Vérifie que entryCount reflète bien le nombre d'écritures postées
7. IMPORTANT : vérifie aussi via une requête SQL brute indépendante
   (SUM(amount_minor) sur reglement_ledger_entry pour ce livre) que ça
   correspond exactement au solde lu via reglement_balance — preuve que
   le trigger et le calcul à froid convergent, cohérent avec la promesse
   du modèle conceptuel ("reconcilie toujours avec SUM(amount_minor) à
   froid")

Test structurel (réflexion, comme déjà fait) : DoctrineReglementBalanceRepository
ne contient aucune trace de INSERT/UPDATE/DELETE sur reglement_balance.

## Documentation
- docs/journal/2026-07-2X-reglement-balance-read.md — inclure
  explicitement le résultat du test de cohérence de bout en bout (preuve
  que le trigger fonctionne correctement à travers tout le cycle
  obligation→crédit)
- docs/STATUS.md : "Règlements : lecture du solde (reglement_balance)
  faite, cohérence bout-en-bout vérifiée. Reste : orchestration
  auto-matching, HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral en
texte brut (pas de pièce jointe, on a eu des soucis de transmission —
découpe en plusieurs messages si besoin) et les résultats, en particulier
le test de cohérence bout en bout qui est le vrai but de cette vague.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
