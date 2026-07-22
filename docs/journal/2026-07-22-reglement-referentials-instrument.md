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
