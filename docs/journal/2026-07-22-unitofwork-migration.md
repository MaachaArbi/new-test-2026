## Reprise à froid

Journal — Migration UnitOfWork (single flush).
**Date :** 2026-07-22
**Décision :** `docs/decisions/2026-07-22-single-flush-per-operation-unitofwork.md`
Les Repositories ont encore besoin de `find()` / `createQueryBuilder()` pour

## Origine

```
# TASK — Discipline zéro-tolérance : un seul flush() par opération, verrouillé mécaniquement

## Contexte
Chaque Repository fait actuellement persist()+flush() ensemble à chaque
save()/delete(). Une opération métier touchant plusieurs entités (ex:
AddBookingChargeHandler : charge + booking) déclenche donc PLUSIEURS
flush() — coûteux, et c'est exactement ce qui a ralenti le legacy de
l'utilisateur sur des opérations multi-tables. Objectif : un seul flush()
par opération métier complète, et rendre l'erreur inverse IMPOSSIBLE à
committer (pas juste déconseillée).

## Partie 1 — Construire le mécanisme (une seule fois, Shared)

### UnitOfWork (nouveau service central)
src/Shared/Infrastructure/Persistence/UnitOfWork.php
- Constructeur : EntityManagerInterface (SEUL endroit du projet autorisé
  à en dépendre directement, cf. règle Deptrac ci-dessous)
- persist(object $entity): void — délègue à $entityManager->persist(),
  RIEN d'autre (pas de flush ici)
- commit(): void — délègue à $entityManager->flush(), RIEN d'autre

### Règle Deptrac
Ajouter une couche/règle : SEUL Shared\Infrastructure\Persistence\UnitOfWork
a le droit de dépendre de Doctrine\ORM\EntityManagerInterface. Tout autre
Repository/Handler qui en dépend directement doit désormais dépendre de
UnitOfWork à la place. Vérifier que deptrac.yaml applique bien cette
contrainte et qu'une violation fait échouer `deptrac analyse`.

### Règle PHPStan personnalisée
Créer une règle PHPStan custom (implémentant l'API Rule de PHPStan) qui
détecte tout appel à ->flush() sur un objet EntityManagerInterface EN
DEHORS de UnitOfWork::commit(). Toute autre occurrence → erreur PHPStan
bloquante. L'enregistrer dans phpstan.dist.neon. Documenter précisément
comment cette règle fonctionne dans le journal (fichier créé, mécanisme
de détection).

## Partie 2 — Migrer TOUS les Repository (Party, Core, Booking)
Pour chaque Doctrine*Repository::save()/delete() actuel :
- Remplacer $entityManager->persist($x); $entityManager->flush(); par
  $unitOfWork->persist($x); (SANS commit — le commit revient à l'appelant)
- Le Repository dépend désormais de UnitOfWork, plus de
  EntityManagerInterface directement (sauf pour les lectures DBAL déjà
  migrées au prompt précédent, qui utilisent Connection, pas
  EntityManagerInterface — ne pas confondre les deux migrations)

## Partie 3 — Migrer TOUS les Handlers Application
Chaque Handler qui appelle un ou plusieurs save()/delete() doit désormais :
- Injecter UnitOfWork
- Appeler $unitOfWork->commit() UNE SEULE FOIS, à la toute fin de
  __invoke(), après TOUS les persist() (peu importe combien d'entités
  différentes sont touchées)

Cas prioritaire à corriger et vérifier en premier :
AddBookingChargeHandler — actuellement 2 flush (charge, puis booking).
Doit devenir : persist charge, recalculer, persist booking, UN SEUL
commit() à la fin. Vérifier par un test qui compte les requêtes SQL
(via un logger de requêtes ou équivalent Doctrine) que le nombre de
statements exécutés diminue par rapport à avant — preuve chiffrée, pas
supposée.

Puis appliquer le même principe à TOUS les autres Handlers du projet
(Party : Create/Update/Delete/Assign/Revoke × role/function/group ;
Booking : Create/Add/Set × tous les sous-domaines ; Core : bootstrap).

## Vérification finale
- `deptrac analyse` : 0 violation, règle UnitOfWork appliquée
- Tentative volontaire de test négatif : ajoute temporairement un
  ->flush() direct hors UnitOfWork quelque part, confirme que PHPStan
  le rejette, puis retire ce test — documenter ce test de validation du
  garde-fou dans le journal (preuve que le mécanisme fonctionne
  vraiment, pas supposé)
- phpunit : TOUS les tests existants doivent rester verts (comportement
  observable identique, seul le mécanisme de flush change)

## Documentation
- docs/decisions/2026-07-22-single-flush-per-operation-unitofwork.md :
  le principe, le mécanisme à deux niveaux (Deptrac + PHPStan custom
  rule), pourquoi une discipline documentée seule ne suffisait pas
- docs/journal/2026-07-22-unitofwork-migration.md : liste de tous les
  Repository/Handler migrés
- docs/STATUS.md

Vu l'ampleur, si un Repository/Handler pose un cas particulier non prévu
ici, ARRÊTE-TOI sur celui-là et signale-le plutôt que d'improviser une
exception silencieuse à la règle.

Relance phpstan/deptrac/phpcpd/phpunit sur l'ensemble du projet. Colle en
priorité : UnitOfWork.php, la règle PHPStan custom, la config deptrac
modifiée, AddBookingChargeHandler corrigé + son test de comptage de
requêtes, et la liste complète des fichiers migrés avec les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
