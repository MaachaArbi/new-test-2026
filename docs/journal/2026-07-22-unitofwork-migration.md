# Journal — Migration UnitOfWork (single flush)

**Date :** 2026-07-22  
**Décision :** `docs/decisions/2026-07-22-single-flush-per-operation-unitofwork.md`

## Cas particulier signalé (pas improvisé en silence)

Les Repositories ont encore besoin de `find()` / `createQueryBuilder()` pour
le chemin load→mutate (L1–L10). Sous la contrainte Deptrac « seul UnitOfWork
dépend de EntityManagerInterface », ces deux méthodes sont **exposées sur
UnitOfWork** comme délégations sans flush. Documenté dans la décision.

## Mécanisme

| Pièce | Rôle |
|-------|------|
| `src/Shared/Infrastructure/Persistence/UnitOfWork.php` | persist / commit / find / createQueryBuilder |
| `deptrac.yaml` | couches `UnitOfWork` + `DoctrineEntityManager` |
| `tools/phpstan/Rules/NoEntityManagerFlushOutsideUnitOfWorkRule.php` | interdit `->flush()` hors `UnitOfWork::commit()` |
| `phpstan.dist.neon` | enregistre la règle |
| `composer.json` autoload-dev `App\PHPStan\` | charge la règle |

### Preuve garde-fou PHPStan

Ajout temporaire d’un `$em->flush()` dans un Repository → `phpstan analyse`
échoue avec `ostravel.emFlushOutsideUnitOfWork` → retrait immédiat.
(Rejoué pendant la vague ; voir section validation ci-dessous.)

## Repositories migrés (18)

### Booking
- DoctrineBookingRepository
- DoctrineBookingFolderRepository
- DoctrineBookingChargeRepository
- DoctrineBookingTravelerRepository
- DoctrineBookingHotelRoomRepository
- DoctrineBookingTransportSegmentRepository
- DoctrineBookingAccommodationDetailRepository
- DoctrineBookingCarRentalDetailRepository
- DoctrineBookingCancellationPolicyRepository
- DoctrineBookingCancellationTierRepository

### Party
- DoctrinePartyAccountRepository
- DoctrinePartyAccountOfficeRepository
- DoctrinePartyAccountOrganizationIdentityRepository
- DoctrinePartyAccountGroupRepository
- DoctrinePartyAccountGroupMembershipRepository
- DoctrinePartyAccountRoleAssignmentRepository
- DoctrinePartyAccountFunctionAssignmentRepository

### Core
- DoctrineCoreCredentialRepository

## Handlers migrés (24) + Commands (3)

### Booking Application
CreateBooking, CreateBookingFolder, CreateBookingTraveler,
AddBookingHotelRoom, AddBookingTransportSegment,
SetBookingAccommodationDetail, SetBookingCarRentalDetail,
CreateBookingCancellationPolicy, AddBookingCancellationTier,
**AddBookingCharge** (SUM DBAL des charges déjà committées + montants
nouvelle charge en mémoire, puis 1 commit),
TransitionBookingStatus, UpdateBookingWorkflow

### Party Application
Create/Update/Delete PartyAccount, CreatePartyAccountGroup,
Assign/Revoke × Role/Function/GroupMembership,
SetPartyAccountOffice, SetPartyAccountOrganizationIdentity

### Commands
BootstrapAgencyAccountCommand, PurgeTestPartyAccountsCommand,
BootstrapAdminCredentialCommand

## AddBookingCharge — preuve chiffrée

Test `AddBookingChargeSingleFlushTest` : service Symfony
`doctrine.debug_data_holder` (DebugMiddleware déjà branché en env test).
Après `reset()` puis handler : exactement **2 écritures** (INSERT charge +
UPDATE booking) dans **un** cycle — baseline pré-migration = 2 flush
séparés.

## Risque résiduel connu

`UnitOfWork` expose `find()` / `createQueryBuilder()` par nécessité
(contrainte Deptrac : seul UoW peut dépendre de `EntityManagerInterface`).
Le garde-fou PHPStan actuel ne bloque **que** `->flush()` hors
`UnitOfWork::commit()`. Aucune règle ne détecte une **lecture pure ORM**
passée par ces méthodes exposées (contournement possible d’ADR-003
« lectures = DBAL »).

À surveiller manuellement en revue (Query handlers / Repositories qui
devraient rester DBAL). Piste future : règle PHPStan supplémentaire
interdisant `UnitOfWork::find()` / `createQueryBuilder()` hors chemins
load→mutate documentés, **si** le risque se matérialise.

## Validation garde-fou PHPStan (preuve exécutée)

1. Injection temporaire dans `DoctrineBookingChargeRepository::save()` :
   `$em->flush()` hors `UnitOfWork::commit()`.
2. `phpstan analyse` sur ce fichier → **ERROR** :
   `ostravel.emFlushOutsideUnitOfWork` — « Appel interdit à
   EntityManager::flush() hors UnitOfWork::commit() ».
3. Fichier restauré immédiatement ; PHPStan repasse vert.

## Correction Deptrac (collecteur)

`type: className` n’existe pas dans cette version Deptrac →
`classNameRegex` pour la couche UnitOfWork ; exclusion Infrastructure via
`must_not: directory …/UnitOfWork.php` (sinon UnitOfWork restait dans
Infrastructure et bloquait les Handlers Application).
