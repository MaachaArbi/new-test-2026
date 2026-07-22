# Backlog — todo

## Party

- [x] `party_account_organization_identity` / `party_account_office` — Domain + Application + Infrastructure (extensions 1-1) — **clos**
- [x] `RevokePartyAccountRole` (Application) — **clos** ; agrégat rôle 100% (assign + revoke)
- [x] Premier Controller Party — GET `/api/v1/party-accounts/{publicId}` + ExceptionListener — **clos** (y compris test 500 générique sans fuite)
- [x] `RevokePartyAccountFunction` / `RevokePartyAccountGroupMembership` (Application) — **clos** ; famille assignations 100% (assign + revoke × 3)
- [x] `POST /api/v1/party-accounts` — création person/organization (DTO validé + Handler) — **clos**
- [x] Liste paginée Party (`GET /api/v1/party-accounts`) — DBAL / ADR-003 — **clos**
- [x] Update/delete PartyAccount HTTP (`PATCH` + `DELETE` soft) — **clos**

## Booking

- [x] `booking_folder` — Domain + Application Create + Infrastructure (mapping Doctrine, soft-delete) — **clos**
- [x] Pivot `booking` (partitionné, colonnes minimales, PK composite Doctrine) — **clos**
- [x] Montants / devises sur booking (`Money`, `ExchangeRate`, `PaymentStatus`) — **clos** (`price_breakdown` JSONB hors map Domain) — clôture `2026-07-22-booking-money-fields-cloture.md`
- [x] Workflow flags booking (`is_on_request`, assignation, lock, dispute, supplier_status_label) — **clos** ; `booking_on_request_flag` hors vague — clôture `2026-07-22-booking-workflow-cloture.md`
- [x] Transitions `status_code` (`transitionTo`, pas de matrice, refus same-status) — **clos** — clôture `2026-07-22-booking-status-transitions-cloture.md`
- [x] Extension hôtel (`BookingAccommodationDetail` 1-1 + `BookingHotelRoom` 1-N) — **clos** — clôture `2026-07-22-booking-hotel-extension-cloture.md`
- [x] `BookingTraveler` (snapshot + pax leader unique par booking) — **clos** — clôture `2026-07-22-booking-traveler-cloture.md`
- [x] `BookingTransportSegment` (flight/train/maritime/transfer) — **clos** — clôture `2026-07-22-booking-transport-segment-cloture.md`
- [x] Référentiel data-driven `booking_service_type_extension` (Assert extension_code, plus de liste PHP) — **clos** — `2026-07-22-booking-service-extension-data-driven.md`
- [x] `BookingCarRentalDetail` — **clos** — clôture `2026-07-22-booking-car-rental-detail-cloture.md`
- [x] Cancellation policy (`BookingCancellationPolicy` + `BookingCancellationTier`) — **clos** — `2026-07-22-booking-cancellation-policy.md`
- [x] Premier Controller Booking — `GET /api/v1/bookings/{publicId}` — **clos** — `2026-07-22-first-booking-controller.md`
- [x] create HTTP Booking — `POST /api/v1/bookings` — **clos** — `2026-07-22-create-booking-endpoint.md`
- [x] list HTTP Booking — `GET /api/v1/bookings` — **clos** — `2026-07-22-list-bookings-endpoint.md`
- [x] add traveler HTTP — `POST /api/v1/bookings/{publicId}/travelers` — **clos** — `2026-07-22-add-booking-traveler-endpoint.md`
- [x] sous-ressources HTTP hotel/transport/car — **clos** — `2026-07-22-booking-subresources-http.md`
- [x] cancellation policy/tier HTTP — **clos** — `2026-07-22-booking-cancellation-http.md`
- [x] `booking_charge` + recalcul totaux (Domain + Application + Infrastructure) — **clos** — `2026-07-22-booking-charge.md`
- [x] `booking_settlement` (assign/revoke historisé, **sans** recalcul Booking) — **clos** — `2026-07-22-booking-settlement.md`
- [x] `booking_payer_split` (plafond ≤ total_vente, historisé) — **clos** — `2026-07-22-booking-payer-split.md`
- [x] Nettoyage cosmétique getters `BookingPayerSplit` (`return $this->x`) — **clos** ; trait `HistorizedBookingChildAccessors` **annulé** (rollback) — `2026-07-22-revert-historized-accessors-trait.md`
- [ ] Clone phpcpd **accepté et documenté** : getters triviaux d'historisation (`id` / `bookingId` / `isActive` / `validFrom` / `validTo` / `createdBy`) dupliqués entre `BookingSettlement` et `BookingPayerSplit`. Extraction commune (trait ou classe abstraite) **PROPOSÉE mais reportée** — décision d'architecture à valider explicitement dans une vague dédiée, pas en réaction automatique à phpcpd. Si un 3ᵉ agrégat historisé Booking apparaît avec le même besoin, reproposer l'extraction à ce moment-là.
- [ ] `booking_payment` (différé — conception provisoire)
- [x] HTTP charges / settlement / payer_split — **clos** — `2026-07-22-booking-financial-http.md` · clôture `2026-07-22-booking-financial-http-cloture.md`
- [ ] Autres endpoints Booking

## Règlements

- [x] Référentiels (`reglement_payment_method`, `reglement_entry_type`) + `reglement_instrument` (Domain + Application création/transition + Infrastructure) — **clos** — `2026-07-22-reglement-referentials-instrument.md`
- [ ] Clone phpcpd **accepté** : getters triviaux `id`/`publicId`/`code`/`label` entre `ReglementPaymentMethod` et `ReglementEntryType` (référentiels seed-only parallèles). Pas d'extraction — surfaces Domain distinctes.
- [ ] Clone phpcpd **accepté** : getters `id`/`publicId`/`partyAccountId`/… entre `ReglementInstrument` et `ReglementLedgerEntry` (agrégats distincts, immutabilité différente). Pas d'extraction.
- [x] Grand livre append-only (`reglement_ledger_entry` + trigger) + obligation depuis Booking + `reglement_post_transfer()` + snapshot `reglement_balance` (trigger) — **clos** — `2026-07-22-reglement-ledger-obligation-transfer.md` · clôture `2026-07-22-reglement-ledger-obligation-transfer-cloture.md`
- [x] Crédit instrument + lettrage `reglement_matching` (plafonds crédit+débit) — **clos** — `2026-07-22-reglement-credit-matching.md` · clôture `2026-07-22-reglement-credit-matching-cloture.md`
- [x] Lecture métier `reglement_balance` (DBAL, jamais d'écriture) — cohérence bout-en-bout trigger ↔ SUM — `2026-07-22-reglement-balance-read.md`
- [x] HTTP instrument / transition / crédit / matching / solde — `2026-07-22-reglements-http.md`
- [ ] Orchestration auto-matching (compose les primitives)
- [ ] Clone phpcpd **accepté** : `BookingHttpSupport` ↔ `ReglementsHttpSupport` (decode+validate+json). Isolation module — pas d'extraction Shared sans vague dédiée.

## Cash Management

- [x] Référentiel routing (`cash_routing_type` + `cash_payment_method_routing`, contrainte croisée Domain) — **clos** — `2026-07-22-cash-payment-method-routing.md`
- [ ] Seed initial `cash_payment_method_routing` pour les modes existants (E/C/V/… selon `payment_method_id`) — suite logique documentée
- [ ] Pivot `cash_session` / `cash_movement` (+ balances/counts)
- [ ] Fonctions PL/pgSQL à appeler (validate / reverse / allocate / …)
- [ ] Banque, dépôts, transmission externe, rapprochement
- [ ] HTTP Cash Management

## Core

- [x] `CoreCredential` Domain (enum provider, port hasher, agrégat, repo) — **clos** ; Session/MFA hors périmètre
- [x] `CoreCredential` Infrastructure (Doctrine mapping + impl Symfony `PasswordHasherInterface`) — **clos**
- [x] Authentification JWT (login Lexik + `SecurityUser` adapter) — **clos**

## Transverse / suite

- [ ] **Rappel permanent ADR-003 / perf** — Avant de clore toute vague touchant à un Repository : vérifier qu'aucune lecture pure (info, vérification d'appartenance/existence, comptage) ne passe par l'ORM — uniquement DBAL. Cf. `docs/decisions/2026-07-22-performance-first-review-criterion.md`
- [ ] ADR-003 différé — **B15–B18** (GetBooking / GetPartyAccount / `requireByPublicId`) et **C3**/Core credentials : coût/bénéfice jugé défavorable pour des lectures indexées unitaires ; à revisiter avec du volume réel. Cf. `docs/journal/2026-07-22-adr003-corrections-applied.md`
- [x] CORS API (`nelmio/cors-bundle`, origines explicites) — **clos**
- [ ] Quand les modules Auth avancée / Provider Integration seront attaqués (hors périmètre actuel) : leurs migrations booking-like (`core_session`, `core_auth_attempt`, `provider_call_log`) devront utiliser `GENERATED BY DEFAULT` dès la première écriture — déjà correct dans `reference/schemas/`, ne pas régénérer depuis une copie plus ancienne. Cf. `docs/decisions/2026-07-21-booking-identity-generated-by-default.md`
- [ ] Import schémas modules suivants (un à la fois)
- [ ] Observabilité — brancher Sentry ou GlitchTip une fois la décision d'infrastructure transverse prise (hors périmètre OsTravel seul)
- [x] Décision extraction VO ouvert — **clos** (`OpenReferentialCode` ; 3ᵉ cas `PartyAccountGroupTypeCode`) — voir décision annotée + journal `2026-07-21-open-referential-code-extraction.md`
- [ ] Amélioration future (non urgente) : test qui scanne automatiquement toutes les DomainException du projet et vérifie qu'aucun errorCode() n'a de traduction manquante — actuellement ErrorsTranslationCatalogueTest ne couvre que les codes explicitement listés à la main, un oubli est possible (cf. party_account_role.assignment_not_found, oublié puis rattrapé en revue le 2026-07-21)
- [ ] Sujet à surveiller — `deleted_at IS NULL` actuellement en dur dans chaque Query DBAL (ex. `ListPartyAccountsHandler`). Si ce filtre se répète sur 3+ Queries futures (Booking, Règlements…), envisager un petit helper partagé plutôt que de le laisser se dupliquer silencieusement module après module.
