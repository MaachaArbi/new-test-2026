## Reprise à froid

Journal — 2026-07-22 — Règlements : référentiels + Instrument.
Première vague du module Règlements. Pose les référentiels seedés (`reglement_payment_method`, `reglement_entry_type`) et l'agrégat `reglement_instrument` (création + transition de statut), sans HTTP. Principe…
- `ReglementPaymentMethod` / `ReglementEntryType` : entités Domain avec `public_id` (contrairement aux référentiels Booking, codes ouverts sans public_id Domain). Lecture / seed uniquement — pas de mutation…

## Origine

```
# TASK — Module Règlements : référentiels + Instrument (Domain + Application + Infrastructure)

## Lecture obligatoire
1. reference/conceptual-models/modele-conceptuel-reglements.md (entier —
   comprendre le principe du grand livre append-only, même si on ne le
   construit pas dans ce prompt)
2. reference/schemas/schema-reglements-v1.sql, tables
   reglement_payment_method, reglement_entry_type, reglement_instrument
3. Booking.php (pattern transitionTo() déjà validé — à répliquer pour
   status_code de l'instrument)

## Portée STRICTEMENT limitée
Référentiels + reglement_instrument (Domain, Application création +
transition de statut, Infrastructure). RIEN d'autre : PAS
reglement_ledger_entry (append-only trigger-enforced — vague séparée, le
vrai risque de ce module), PAS reglement_balance, PAS reglement_matching,
PAS reglement_transfer, PAS de fonction SQL reglement_post_transfer().
PAS de HTTP dans ce prompt.

## Domain — Référentiels (contrairement à Booking, CES tables ont public_id)
src/Modules/Reglements/Domain/Entity/ReglementPaymentMethod.php
- id, publicId, code (VARCHAR(4)), label, isCashLike, isActive
- Pas de mutation dans cette vague (lecture/seed uniquement — le
  référentiel est déjà seedé par migration SQL, pas créé via Application
  ici)

src/Modules/Reglements/Domain/Entity/ReglementEntryType.php
- id, publicId, code, label, normalSign (int, -1 ou 1)
- Même remarque : lecture seule dans cette vague

## Domain — PartyRole (instrument) — enum PHP natif
src/Modules/Reglements/Domain/ValueObject/InstrumentPartyRole.php
enum : Client = 'client', Fournisseur = 'fournisseur'
(CHECK SQL fixe à 2 valeurs, pas un référentiel ouvert — même logique que
PaymentStatus/BeneficiaryRole déjà faits)

## Domain — ReglementInstrumentStatus — enum PHP natif
src/Modules/Reglements/Domain/ValueObject/ReglementInstrumentStatus.php
enum : Active = 'active', Returned = 'returned', Cancelled = 'cancelled'

## Domain — ReglementInstrument (agrégat, création + transition)
src/Modules/Reglements/Domain/Entity/ReglementInstrument.php
- id, publicId, partyAccountId, partyRole (InstrumentPartyRole),
  currencyCode, paymentMethodId, amountMinor (int, > 0 — CHECK miroir en
  Domain, immuable après création, JAMAIS de setter dessus), instrumentRef
  (?string), bankName/dueDate/issuedOn (?string/?DateTimeImmutable),
  metadata (array), statusCode (ReglementInstrumentStatus, défaut Active),
  statusChangedAt (?DateTimeImmutable), statusReason (?string),
  officeAccountId (?int)
- create(...) : factory, statusCode toujours Active à la création
- transitionStatus(ReglementInstrumentStatus $newStatus, ?string $reason):
  void — met à jour statusCode + statusChangedAt=now() + statusReason.
  PAS de validation de transition complexe dans cette vague (même
  philosophie que Booking::transitionTo() — pas de matrice inventée),
  mais noter explicitement dans le journal : "un retour/annulation
  d'instrument doit normalement déclencher une écriture inverse dans le
  grand livre — HORS PÉRIMÈTRE ici, le grand livre n'existe pas encore.
  Cette méthode pose uniquement la mutation du statut de l'instrument
  lui-même, l'orchestration complète (écriture inverse) viendra avec la
  vague ledger_entry."

## Repository
ReglementPaymentMethodRepositoryInterface / ReglementEntryTypeRepositoryInterface :
findByCode (lecture simple)
ReglementInstrumentRepositoryInterface : findById, findByPublicId, save
(via UnitOfWork, cohérent avec la discipline déjà en place)

## Application
CreateReglementInstrument/{Command,Handler} :
- Vérifie que paymentMethodId existe et est actif (lecture DBAL)
- Vérifie partyRole cohérent avec la nature du party_account si une règle
  simple existe déjà ailleurs (sinon ne rien inventer, juste créer)
- assign() + commit() unique

TransitionReglementInstrumentStatus/{Command,Handler} :
- findById → transitionStatus() → commit unique

## Tests (PostgreSQL réel)
- Création instrument → round-trip complet, tous les champs, statusCode
  Active par défaut
- amountMinor <= 0 → rejeté par le Domain avant SQL (miroir CHECK)
- Transition Active → Returned → vérifie statusChangedAt/statusReason
  persistés
- metadata JSONB : round-trip d'un contenu non-trivial

## Documentation
- docs/journal/2026-07-2X-reglement-referentials-instrument.md — inclure
  explicitement la section "Hors périmètre volontaire" (grand livre,
  balance, matching, transfer, fonction SQL) avec la raison (isoler le
  risque append-only/trigger comme fait pour le pivot Booking)
- docs/STATUS.md : nouvelle ligne module Règlements — "Référentiels +
  Instrument faits (création + transition statut). Reste : grand livre
  append-only (trigger), balance (trigger), matching, transfer, HTTP."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Règlements : référentiels + Instrument

## Contexte

Première vague du module Règlements. Pose les référentiels seedés
(`reglement_payment_method`, `reglement_entry_type`) et l'agrégat
`reglement_instrument` (création + transition de statut), sans HTTP.

Principe directeur rappelé (modèle conceptuel) : le grand livre est
append-only ; l'instrument n'est **pas** une écriture — il *produit*
des crédits via le grand livre. Cette vague s'arrête volontairement
avant ce cœur à risque.

## Domain

- `ReglementPaymentMethod` / `ReglementEntryType` : entités Domain avec
  `public_id` (contrairement aux référentiels Booking, codes ouverts sans
  public_id Domain). Lecture / seed uniquement — pas de mutation
  Application.
- Enums natifs fermés (CHECK SQL) : `InstrumentPartyRole`
  (client/fournisseur), `ReglementInstrumentStatus`
  (active/returned/cancelled).
- `ReglementInstrument::create()` : `statusCode = Active`,
  `amountMinor > 0` (miroir CHECK SQL), **aucun setter** sur
  `amountMinor`.
- `transitionStatus()` : pas de matrice. Met à jour `statusChangedAt` /
  `statusReason`. Refus du no-op (same-status) : **choix délibéré** par
  cohérence avec la convention déjà établie sur Party
  (`RevokePartyAccountRole`) et reprise sur Booking
  (`transitionTo`) — **PAS** une règle métier Règlements confirmée par
  l'utilisateur. Si un jour le comportement réel du terrain contredit
  ce choix (comme pour les statuts Booking), il faudra le revisiter.

### Note périmètre sur `transitionStatus()`

Un retour/annulation d'instrument doit normalement déclencher une
écriture inverse dans le grand livre — **HORS PÉRIMÈTRE** ici, le grand
livre n'existe pas encore. Cette méthode pose uniquement la mutation du
statut de l'instrument lui-même ; l'orchestration complète (écriture
inverse) viendra avec la vague `ledger_entry`.

## Application

- `CreateReglementInstrumentHandler` : vérifie payment method actif +
  devise (DBAL ADR-003) → `create` → `save` → `commit` unique.
  Aucune règle inventée party_role ↔ nature party_account
  (nature = person/organization ; client/fournisseur = rôles).
- `TransitionReglementInstrumentStatusHandler` : find →
  `transitionStatus` → `commit` unique.

## Infrastructure

- Migration slice `Version20260722170000` : 3 tables + seeds uniquement.
- Mappings XML Doctrine + repos UnitOfWork (`persist` ; flush = Handler).

## Hors périmètre volontaire

| Élément | Pourquoi reporté |
|---|---|
| `reglement_ledger_entry` + trigger append-only | Vrai risque du module — isoler comme le pivot Booking |
| `reglement_balance` + trigger incrémental | Dépend du grand livre |
| `reglement_matching` | Overlay optionnel sur le livre |
| `reglement_transfer` + `reglement_post_transfer()` | Atomicité multi-jambes ; dépend du livre |
| HTTP | Vague ultérieure une fois le Domain/Infra instrument stables |

## Tests

- Unit Domain : amount ≤ 0 rejeté, Active par défaut, transition +
  same-status.
- Integration Postgres : seeds lisibles, round-trip tous champs,
  metadata JSONB non-trivial, Active→Returned avec audit persisté.

## Qualité

- phpstan / deptrac / phpunit : OK après correctifs test (JSONB key order,
  assertNotNull PublicId).
- phpcpd : clone getters `id`/`publicId`/`code`/`label` entre les deux
  référentiels seed-only — **accepté** (cf. todo).
