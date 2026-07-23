## Reprise à froid

Journal — Audit complet ADR-003 (lectures ORM).
**Date :** 2026-07-22
**Statut :** INVENTAIRE — **en attente de validation** (aucune correction appliquée dans cette étape)
**Décision liée :** `docs/decisions/2026-07-22-performance-first-review-criterion.md`

## Origine

```
# TASK — Audit complet ADR-003 : éliminer toute lecture ORM restante

## Principe à graver dans le projet (pas juste corriger ce prompt-ci)
Créer docs/decisions/2026-07-22-performance-first-review-criterion.md :
« Priorité #1 non négociable du projet : PERFORMANCE. Priorité #2 :
SÉCURITÉ. Toute décision de conception qui les compromet doit être
signalée explicitement, jamais glissée en silence. Rappel opérationnel
concret (ADR-003) : AUCUNE lecture ne doit passer par Doctrine ORM
(EntityManager, QueryBuilder ->select(entité)), uniquement par DBAL
(Connection, SQL direct). Exception légitime : charger une entité via
ORM est acceptable UNIQUEMENT quand l'objectif est de la MUTER puis la
sauvegarder (ex: findById() avant recalculateTotals()+save()) — jamais
pour une simple lecture d'information ou une vérification d'appartenance/
existence. »

Ajouter une ligne équivalente dans docs/backlog/todo.md, section
Transverse, comme rappel permanent à vérifier à chaque nouvelle vague :
« Avant de clore toute vague touchant à un Repository : vérifier qu'aucune
lecture pure (info, vérification d'appartenance/existence, comptage)
ne passe par l'ORM — uniquement DBAL. »

## Audit — chercher CHAQUE occurrence dans tout le projet (Party, Core, Booking)
Grep systématique de tous les Repository (Doctrine*.php) et Application
(*Handler.php, Assert*.php) pour :
- createQueryBuilder()->select('alias')->from(Entité::class, 'alias')
  utilisé pour une lecture pure (pas suivi d'une mutation+save sur CE
  MÊME objet)
- $this->entityManager->find(Entité::class, ...) utilisé pour une
  vérification (ex: "existe-t-il", "appartient-il à") plutôt que pour
  charger-muter-sauvegarder
- Toute méthode findByXxx() dont le SEUL usage dans le code est une
  boucle de comparaison (ex: assertRoomBelongsToBooking,
  assertTravelerBelongsToBooking, assertSegmentBelongsToBooking — charger
  toute la collection via ORM puis chercher un ID dedans, au lieu d'un
  EXISTS/COUNT DBAL ciblé sur la paire exacte)

Lister CHAQUE occurrence trouvée dans le journal AVANT de corriger quoi
que ce soit — un inventaire complet d'abord, pour que je puisse le
vérifier, plutôt que des corrections dispersées difficiles à auditer.

## Corrections attendues (exemples déjà identifiés, à confirmer/étendre)
1. bookingCurrencies() — déjà spécifié (fait précédemment, vérifier que
   c'est bien fait si pas encore appliqué)
2. assertRoomBelongsToBooking (BookingCancellationPolicy) : remplacer la
   boucle sur collection ORM par une requête DBAL ciblée
   (SELECT 1 FROM booking_hotel_room WHERE id = :roomId AND booking_id =
   :bookingId), pas de chargement de collection
3. assertTravelerBelongsToBooking / assertSegmentBelongsToBooking
   (AddBookingCharge) : même traitement — requête DBAL EXISTS ciblée sur
   la paire exacte, jamais charger toute la liste pour boucler en PHP
4. Tout autre cas trouvé par l'audit (Party inclus — vérifier
   spécifiquement les méthodes d'appartenance déjà écrites ce matin,
   comme sur PartyAccountGroup si un pattern similaire existe)

## Ce qui reste légitimement en ORM (ne pas toucher)
- Tout findById()/findByPublicId() dont le résultat est ensuite muté puis
  sauvegardé dans le même Handler (ex: recalculateTotals(), disable(),
  revoke()...) — c'est l'usage normal de l'ORM pour les écritures

## Tests
Pour chaque correction : vérifier qu'aucun test existant ne casse
(comportement observable identique), et si un test vérifiait le nombre de
requêtes ou un comportement N+1, l'ajuster si besoin. Pas de nouveaux
tests de comportement nécessaires (le résultat métier ne change pas),
mais ajouter si pertinent un commentaire dans le test existant confirmant
"DBAL après correction".

## Documentation
- docs/journal/2026-07-22-adr003-full-audit.md : liste complète des
  occurrences trouvées, celles corrigées, celles jugées légitimes et
  pourquoi
- docs/STATUS.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral du
journal d'audit AVANT toute correction (pour validation de l'inventaire),
puis les fichiers corrigés une fois validés.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — Audit complet ADR-003 (lectures ORM)

**Date :** 2026-07-22  
**Statut :** INVENTAIRE — **en attente de validation** (aucune correction appliquée dans cette étape)  
**Décision liée :** `docs/decisions/2026-07-22-performance-first-review-criterion.md`

## Méthode

Grep systématique sur `src/` :

- `Doctrine*.php` (repositories)
- `*Handler.php`, `Assert*.php`
- Controllers / HttpSupport / Commands qui appellent ces méthodes
- Motifs : `createQueryBuilder`, `->find(`, `findByXxx`, boucles d’appartenance

Classification :

| Tag | Sens |
|-----|------|
| **V** | VIOLATION — lecture pure ORM (info, existence, appartenance, COUNT) |
| **L** | LÉGITIME — load ORM → mutate → save du **même** objet |
| **A** | AMBIGU / dual-use — à trancher (souvent GET historique ou `exists*` via charge pleine) |

---

## Inventaire — occurrences à corriger (V)

### Booking — appartenance / hydratation (suspects du prompt + confirmés)

| # | Fichier | Méthode / site | Mécanisme | Pourquoi V |
|---|---------|----------------|-----------|------------|
| B1 | `DoctrineBookingChargeRepository` | `bookingCurrencies()` L54–73 | QB `select(Booking)` | Lit 2 devises pour hydrate — pas de mutate booking |
| B2 | `DoctrineBookingChargeRepository` | `findByBookingId()` L22–43 | QB `select(BookingCharge)` | Liste pure (prod : aucun caller write ; tests + hydrate via B1) |
| B3 | `CreateBookingCancellationPolicyHandler` | `assertRoomBelongsToBooking` L42–52 | `roomRepository->findByBookingId` + boucle PHP | Appartenance — doit être `EXISTS` DBAL ciblé |
| B4 | `AddBookingChargeHandler` | `assertTravelerBelongsToBooking` L76–85 | `travelerRepository->findByBookingId` + boucle | Idem |
| B5 | `AddBookingChargeHandler` | `assertSegmentBelongsToBooking` L87–96 | `segmentRepository->findByBookingId` + boucle | Idem |
| B6 | `AssertBookingServiceType` | `__invoke` L26–31 | `bookingRepository->findById` | Lit seulement `serviceTypeCode` (extension check déjà en DBAL) |
| B7 | `DoctrineBookingTravelerRepository` | `hasActivePaxLeader` L36–48 | COUNT via ORM QB | Existence / unicité — DBAL |
| B8 | `CreateBookingTravelerHandler` | L23–25 | appelle B7 | Caller de B7 |
| B9 | `DoctrineBookingFolderRepository` | `existsByReferenceCode` L42–51 | COUNT ORM QB | Existence — DBAL |
| B10 | `CreateBookingFolderHandler` | L27–29 | appelle B9 | Caller de B9 |
| B11 | `DoctrineBookingCancellationPolicyRepository` | `existsForBooking` / `existsForRoom` | délègue à `findBy*` entité pleine | Existence via hydrate ORM |
| B12 | `CreateBookingCancellationPolicyHandler` | L29–33 | appelle B11 | Caller de B11 |
| B13 | `AddBookingCancellationTierHandler` | L25–27 | `policyRepository->findById` null-check | Existence pure (pas de mutate policy) |
| B14 | `AddBookingCancellationTierController` | L54–57 | `findById` + compare `bookingId` | Appartenance / resolve — pas mutate |
| B15 | `BookingHttpSupport::requireByPublicId` | L28–35 | `findByPublicId` | Resolve `publicId` → id sans mutate booking |
| B16 | `AddBookingTravelerController` | L49–54 | `findByPublicId` inline | Même pattern que B15 |
| B17 | `GetBookingController` | L37–48 | `findByPublicId` → JSON | Lecture affichage (commentaire historique « acceptable » **obsolète** au regard de la décision 2026-07-22) |
| B18 | `GetPartyAccountController` | (Party) | `findByPublicId` → JSON | Idem GET 1-ligne — **V** sous critère performance-first |

Callers de B15 (tous **V** via résolution) : CreateCancellationPolicy, AddHotelRoom, AddTransportSegment, SetCarRental, AddCancellationTier controllers.

### Booking — méthodes ORM « liste » sans caller write prod (API / tests)

| # | Fichier | Méthode | Note |
|---|---------|---------|------|
| B19 | `DoctrineBookingHotelRoomRepository` | `findByBookingId` | **Seul usage prod = B3** (appartenance). Tests listent aussi. |
| B20 | `DoctrineBookingTravelerRepository` | `findByBookingId` | **Seul usage prod = B4** |
| B21 | `DoctrineBookingTransportSegmentRepository` | `findByBookingId` | **Seul usage prod = B5** |
| B22 | `DoctrineBookingCancellationTierRepository` | `findByPolicyId` | Tests only en prod-code |
| B23 | `DoctrineBookingCarRentalDetailRepository` | `findByBookingId` | Tests only (`Set*` ne charge pas) |
| B24 | `DoctrineBookingAccommodationDetailRepository` | `findByBookingId` | Tests only |
| B25 | `DoctrineBookingFolderRepository` | `findById` / `findByPublicId` | Aucun caller `src/` hors tests potentiels |

> B19–B21 ne sont pas « dual-use list HTTP » aujourd’hui : en prod ils ne servent qu’aux boucles d’appartenance. Après correction B3–B5, décider si on garde une API ORM `findByBookingId` pour un futur GET liste (alors **V** tant qu’elle reste ORM) ou on la remplace par DBAL dès le premier endpoint.

### Party — existence / COUNT / nature

| # | Fichier | Méthode / site | Mécanisme | Pourquoi V |
|---|---------|----------------|-----------|------------|
| P1 | `DoctrinePartyAccountGroupMembershipRepository` | `hasActiveMembership` | COUNT ORM | Anti-doublon assign |
| P2 | `AssignPartyAccountGroupMembershipHandler` | | appelle P1 | |
| P3 | `DoctrinePartyAccountRoleAssignmentRepository` | `hasActiveRole` | COUNT ORM | |
| P4 | `AssignPartyAccountRoleHandler` | | appelle P3 | |
| P5 | `DoctrinePartyAccountFunctionAssignmentRepository` | `hasActiveFunction` | COUNT ORM | |
| P6 | `AssignPartyAccountFunctionHandler` | | appelle P5 | |
| P7 | `DoctrinePartyAccountGroupRepository` | `existsByTypeAndName` | COUNT ORM | Unicité create group |
| P8 | `CreatePartyAccountGroupHandler` | | appelle P7 | |
| P9 | `DoctrinePartyAccountOfficeRepository` | `existsByOfficeCode` | COUNT ORM | |
| P10 | `SetPartyAccountOfficeHandler` | | `existsByOfficeCode` + `accountRepository->findById` | Existence code + **nature** compte sans mutate |
| P11 | `SetPartyAccountOrganizationIdentityHandler` | `assertAccountIsOrganization` | `findById` | Lit nature, pas mutate |
| P12 | `DoctrinePartyAccountOfficeRepository` | `findByAccountId` | `em->find` | Existence pure — `BootstrapAgencyAccountCommand` |
| P13 | `DoctrinePartyAccountOrganizationIdentityRepository` | `findByAccountId` | `em->find` | Existence pure — bootstrap |
| P14 | `DoctrinePartyAccountRepository` | `findByEmail` | QB select entity | Auth / bootstrap — lecture pure |
| P15 | `CoreCredentialUserProvider` | | `findByEmail` + credentials | Auth |
| P16 | `BootstrapAdminCredentialCommand` | | `findByEmail` + `findActiveByAccountId` | Bootstrap |
| P17 | `BootstrapAgencyAccountCommand::findAgencyAccount` | L95–106 | QB select `PartyAccount` | Lookup sans mutate |
| P18 | `DoctrinePartyAccountGroupRepository::findById` | | `em->find` | Aucun caller prod mutate ; tests only — API lecture |

### Core

| # | Fichier | Méthode | Pourquoi V |
|---|---------|---------|------------|
| C1 | `DoctrineCoreCredentialRepository::findById` | `em->find` | API lecture ; callers = tests |
| C2 | `DoctrineCoreCredentialRepository::findByProviderIdentity` | QB entity | Tests ; lecture pure |
| C3 | `DoctrineCoreCredentialRepository::findActiveByAccountId` | QB list entities | Auth / bootstrap (P15–P16) |

---

## Inventaire — légitimes (L) — ne pas toucher

| # | Fichier | Flux | Pourquoi L |
|---|---------|------|------------|
| L1 | `AddBookingChargeHandler` | `bookingRepository->findById` → `recalculateTotals` → `save` | Mute booking |
| L2 | `TransitionBookingStatusHandler` | `findById` → `transitionTo` → `save` | |
| L3 | `UpdateBookingWorkflowHandler` | `findById` → mutations → `save` | |
| L4 | `UpdatePartyAccountHandler` | `findByPublicId` → mutate → `save` | |
| L5 | `DeletePartyAccountHandler` | `findByPublicIdIncludingDeleted` → `delete` | Soft-delete |
| L6 | `RevokePartyAccountRoleHandler` | `findById` → `revoke` | |
| L7 | `RevokePartyAccountFunctionHandler` | idem | |
| L8 | `RevokePartyAccountGroupMembershipHandler` | idem | |
| L9 | `PurgeTestPartyAccountsCommand` | `findById` → soft-delete (batch) | Mute |
| L10 | Tous les `save` / `persist` / `flush` / `create*` sans lecture préalable | écriture | hors scope lecture |

**Note dual-use :** `PartyAccountRepository::findByPublicId` et `BookingRepository::findById` / `findByPublicId` sont **L** ou **V** selon le caller (voir B6, B15–B17, P10–P11, L1–L4).

---

## Inventaire — déjà conformes (DBAL)

| Zone | Détail |
|------|--------|
| `ListPartyAccountsHandler` | SQL DBAL paginé |
| `ListBookingsHandler` | SQL DBAL paginé |
| `BookingReferentialValidator` | `EXISTS` DBAL sur référentiels |
| `AssertBookingServiceType` (partie extension) | `SELECT 1 FROM booking_service_type_extension` DBAL — **mais** le load booking en amont est **V** (B6) |
| `AddBookingChargeHandler::sumAmountsForBooking` | `SUM` DBAL — conforme |
| `PurgeTestPartyAccountsCommand` (sélection candidats) | DBAL — conforme ; soft-delete via ORM = L |

---

## Plan de correction proposé (après validation inventaire)

Ordre suggéré (impact / clarté) :

1. **Appartenance Booking** — B3, B4, B5 → `SELECT 1 … WHERE id = :x AND booking_id = :bookingId` (DBAL dans Handler ou petite méthode repo DBAL).
2. **`bookingCurrencies`** — B1 → DBAL scalar sur `booking` (B2 reste pour tests jusqu’à HTTP charges ; alors DBAL liste).
3. **Existence Booking** — B7–B13 (pax leader, reference code, exists policy, findById null-check).
4. **AssertBookingServiceType** — B6 → `SELECT service_type_code FROM booking WHERE id = ?` DBAL.
5. **Resolve publicId** — B15–B16 (+ éventuellement B17 GET) → `SELECT id …` / projection DBAL.
6. **Party COUNT / exists / nature** — P1–P13.
7. **Auth / Core / Bootstrap** — P14–P17, C1–C3 (plus sensible : UserProvider).

## Corrections appliquées

*Voir `docs/journal/2026-07-22-adr003-corrections-applied.md` (portée validée B1, B3–B13, P1–P13).*

## Suite

B15–B18 / C1–C3 différés (coût/bénéfice). Relancer qualité après chaque vague Repository.
