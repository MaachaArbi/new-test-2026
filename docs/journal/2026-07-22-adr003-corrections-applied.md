# Journal — Corrections ADR-003 appliquées

**Date :** 2026-07-22  
**Inventaire :** `docs/journal/2026-07-22-adr003-full-audit.md`  
**Décision :** `docs/decisions/2026-07-22-performance-first-review-criterion.md`  
**Portée validée :** B1, B3–B13, P1–P13 (existence) — **hors scope** B15–B18, C1–C3, L1–L10

## Corrections appliquées

### Booking — appartenance

| ID | Changement |
|----|------------|
| **B3** | `BookingHotelRoomRepository::belongsToBooking` (DBAL `SELECT 1 … id + booking_id`) ; `CreateBookingCancellationPolicyHandler` n’appelle plus `findByBookingId` + boucle |
| **B4** | `BookingTravelerRepository::belongsToBooking` (DBAL) ; `AddBookingChargeHandler` |
| **B5** | `BookingTransportSegmentRepository::belongsToBooking` (DBAL) ; `AddBookingChargeHandler` |

### Booking — hydratation / assert / existence

| ID | Changement |
|----|------------|
| **B1** | `DoctrineBookingChargeRepository::bookingCurrencies` → DBAL `SELECT achat_currency_code, vente_currency_code` |
| **B6** | `AssertBookingServiceType` : plus de `findById` ORM ; `SELECT service_type_code FROM booking` + extension déjà DBAL |
| **B7+B8** | `hasActivePaxLeader` → DBAL `SELECT 1 … is_pax_leader` (signature inchangée) |
| **B9+B10** | `existsByReferenceCode` → DBAL `SELECT 1` (signature inchangée) |
| **B11+B12** | `existsForBooking` / `existsForRoom` → DBAL `SELECT 1` (plus de délégué `findBy*` entité) |
| **B13** | `existsById` DBAL + `AddBookingCancellationTierHandler` utilise `existsById` (plus de null-check `findById`) |

### Party — existence / COUNT / nature

| ID | Changement |
|----|------------|
| **P1+P2** | `hasActiveMembership` → DBAL |
| **P3+P4** | `hasActiveRole` → DBAL |
| **P5+P6** | `hasActiveFunction` → DBAL |
| **P7+P8** | `existsByTypeAndName` → DBAL |
| **P9** | `existsByOfficeCode` → DBAL |
| **P10** | `existsByOfficeCode` DBAL + `findNatureById` DBAL dans `SetPartyAccountOfficeHandler` (plus de `findById` pour nature) — le compte n’est **pas** muté |
| **P11** | `findNatureById` DBAL dans `SetPartyAccountOrganizationIdentityHandler` — **cas par cas** : `findById` ne servait **pas** à muter le compte → remplacé |
| **P12** | `PartyAccountOfficeRepository::existsByAccountId` DBAL ; bootstrap utilise `existsByAccountId` |
| **P13** | `PartyAccountOrganizationIdentityRepository::existsByAccountId` DBAL ; bootstrap idem |

`findById` PartyAccount / `findByAccountId` Office|Identity restent ORM pour les usages **load→mutate** (Purge) ou reload tests — hors existence pure.

## Différé (validé)

- **B15–B18** : resolve/GET `publicId` — coût/bénéfice défavorable pour lectures indexées unitaires ; revisiter avec volume réel
- **C1–C3** : Core credentials — différé

## Grep de contrôle (après correction)

Cibles vérifiées : plus de QB `select(Entité)` / `em->find` **dans** les méthodes corrigées ci-dessus.

```
# Méthodes existence/appartenance corrigées — aucune hydratation ORM résiduelle
rg -n "createQueryBuilder|entityManager->find|->find\(" \
  src/Modules/Booking/Infrastructure/Persistence/DoctrineBookingTravelerRepository.php \
  src/Modules/Booking/Infrastructure/Persistence/DoctrineBookingHotelRoomRepository.php \
  src/Modules/Booking/Infrastructure/Persistence/DoctrineBookingTransportSegmentRepository.php \
  src/Modules/Booking/Infrastructure/Persistence/DoctrineBookingChargeRepository.php \
  src/Modules/Booking/Infrastructure/Persistence/DoctrineBookingFolderRepository.php \
  src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountGroupMembershipRepository.php \
  src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountRoleAssignmentRepository.php \
  src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountFunctionAssignmentRepository.php \
  src/Modules/Party/Infrastructure/Persistence/DoctrinePartyAccountGroupRepository.php
```

Attendu : seuls les `findBy*` / `findById` **non corrigés** (listes ORM légitimes pour tests/futur, ou load mutate) apparaissent — **pas** dans `belongsToBooking`, `hasActive*`, `exists*`, `bookingCurrencies`, `hasActivePaxLeader`, `existsByReferenceCode`.

Handlers : plus de boucles `findByBookingId` pour appartenance ; `AssertBookingServiceType` sans `BookingRepositoryInterface`.

## Delta phpunit 1600 → 1603 (clarification 2026-07-22)

Recherche effectuée (pas une supposition) :

1. `BookingServiceTypeExtensionDataDrivenTest` (seul fichier touché par le
   rewiring Assert) : **6 assertions aujourd’hui** (`--testdox`), inchangé
   en nombre de `self::assert*` dans le source → **éliminé** comme cause.
2. Aucune ligne `self::assert*` ajoutée/modifiée dans les tests pendant
   ADR-003 (seul le constructeur `AssertBookingServiceType`).
3. Seul site du projet avec **exactement +3 asserts conditionnels** :
   `ListBookingsControllerTest::test_explain_date_filter_partition_pruning_trace`
   (L218–224) — `assertNotSame` toujours, puis si le plan EXPLAIN cite une
   partition enfant : `assertTrue(august)` + `assertFalse(july)` +
   `assertFalse(september)`.
4. Run actuel : ce test = **4 assertions** (branche prise :
   `Pruning observation: august=yes july=no september=no`). Si le plan PG
   ne citait pas les enfants au run « 1600 », le test ne compterait que
   **1** assert → écart suite = **+3**.

**Conclusion :** le delta n’est **pas** causé par la migration DBAL ADR-003 ;
il est expliqué par la variance du format/contenu EXPLAIN PostgreSQL sur ce
test « soft observation » (commentaire L203 du test). Pas d’artefact JUnit
du run 1600 pour prouver rétroactivement que la branche était inactive, mais
aucune autre cause candidate trouvée après inventaire des asserts
conditionnels et du rewiring Assert.

## Note tables Party (DBAL)

Les noms SQL réels (mapping ORM) :

- `party_account_role` (entité RoleAssignment)
- `party_account_function` (entité FunctionAssignment)
- `party_account_group_member` (entité GroupMembership)

Ne pas utiliser les suffixes `_assignment` / `_membership` issus des noms de classes PHP.
