## Reprise à froid

Journal — 2026-07-21 — Infrastructure PartyAccountRoleAssignment.
Infrastructure + Application pour l’assignation de rôle. La contrainte DB `uq_party_account_role_active` reste un **filet** ; la règle métier est vérifiée en PHP dans `AssignPartyAccountRoleHandler` (ADR-002).
Infrastructure + Application pour l’assignation de rôle. La contrainte DB
`uq_party_account_role_active` reste un **filet** ; la règle métier est vérifiée

## Origine

```
# TASK — Module Party : Infrastructure PartyAccountRoleAssignment + prévention doublon actif

## Lecture obligatoire
1. src/Modules/Party/Domain/Entity/PartyAccountRoleAssignment.php (déjà existant,
   ne pas le modifier sauf nécessité de mapping — dans ce cas ARRÊTE-TOI et signale)
2. reference/schemas/schema-party-account-v1.sql : uq_party_account_role_active
   (index partiel sur (account_id, role_code) WHERE valid_to IS NULL) — c'est le
   FILET de sécurité DB, pas la source de la règle métier
3. reference/backend-cadrage/01-backend-architecture-decisions.md (ADR-002 : la
   vraie logique métier vit en Application/Domain, la DB ne porte que les
   invariants structurels)

## Principe à respecter strictement
La règle "un compte ne peut pas avoir le même rôle actif deux fois" doit être
vérifiée EXPLICITEMENT en PHP (lecture avant écriture), dans une couche
Application dédiée — PAS uniquement laissée à la contrainte DB qui, elle,
lèvera une erreur SQL brute (violation de contrainte unique) si jamais atteinte,
ce qui ne doit être qu'un filet de sécurité de dernier recours, jamais le
mécanisme de validation principal côté utilisateur.

## 1. Mapping Doctrine XML
config/doctrine/mappings/PartyAccountRoleAssignment.orm.xml — mapper id,
accountId, roleCode (Value Object PartyRoleCode — décider : Doctrine Type custom
comme Email/PublicId, cohérent avec le pattern déjà établi), validFrom, validTo,
createdBy. Constructeur privé : reflection, pas de setters ajoutés au Domain.
Enregistrer le mapping dans config/packages/doctrine.yaml selon le pattern déjà
documenté (commentaire "Pattern pour un futur module").

## 2. Repository (Infrastructure, mécanique pure)
DoctrinePartyAccountRoleAssignmentRepository implémentant
PartyAccountRoleAssignmentRepositoryInterface (assign = persist+flush, revoke =
flush après mutation déjà faite par le Domain). Ajouter UNE méthode de lecture
nécessaire à la vérification (ex: hasActiveRole(int $accountId, PartyRoleCode
$roleCode): bool) — ajouter cette méthode à l'interface Domain si besoin, avec
justification dans le journal (ce n'est pas une violation du principe "pas de
delete/update générique", c'est une lecture, catégorie différente).

## 3. Application (c'est ICI que vit la règle métier, pas dans le Repository)
src/Modules/Party/Application/AssignPartyAccountRole/
  - AssignPartyAccountRoleCommand.php (accountId, roleCode, createdBy)
  - AssignPartyAccountRoleHandler.php : vérifie hasActiveRole() AVANT de créer
    l'assignation ; si déjà actif, lève une exception Domain dédiée (créer
    PartyAccountRoleAlreadyActiveException si elle n'existe pas, même famille
    DomainException avec errorCode()) — PAS l'erreur SQL brute qui remonterait
    si on laissait juste la contrainte DB réagir.

## 4. Tests d'intégration (PostgreSQL réel)
- Assignation simple : round-trip complet (assign → persist → findById via une
  requête simple → champs identiques)
- Revoke : assignation active revoquée, valid_to bien persisté
- LE test qui compte : tenter d'assigner deux fois le même rôle actif au même
  compte via le Handler → doit lever l'exception Application/Domain AVANT
  d'atteindre la base (donc AVANT toute violation de contrainte SQL) — vérifier
  qu'aucune ligne en trop n'est créée
- Un test qui vérifie qu'on PEUT réassigner le même rôle après une révocation
  (nouvelle ligne, ancienne conservée avec son valid_to — jamais d'UPDATE sur
  le contenu, cohérent avec la décision #11)

## Documentation
- docs/journal/2026-07-2X-party-role-assignment-infrastructure.md
- docs/STATUS.md : assignation rôle complète (Domain + Application + Infrastructure)
- docs/backlog/todo.md : function et group restent, "même pattern maintenant
  éprouvé (Domain + Application avec vérification explicite + Infrastructure)"

Relance phpstan/deptrac/phpcpd/phpunit (Unit + Integration). Colle le contenu de
tous les fichiers créés et les résultats. Si deptrac signale une violation liée
à l'ajout d'une couche Application (règle peut-être pas encore posée pour ce
niveau), ARRÊTE-TOI et signale-le plutôt que d'assouplir la règle toi-même.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Infrastructure PartyAccountRoleAssignment

## Contexte

Infrastructure + Application pour l’assignation de rôle. La contrainte DB
`uq_party_account_role_active` reste un **filet** ; la règle métier est vérifiée
en PHP dans `AssignPartyAccountRoleHandler` (ADR-002).

## Faits

1. Mapping XML `PartyAccountRoleAssignment.orm.xml` + type DBAL `party_role_code`
2. `DoctrinePartyAccountRoleAssignmentRepository` : `assign` / `revoke` / `findById`
   / `hasActiveRole` — lecture `hasActiveRole` ajoutée à l’interface Domain
   (justification : nécessaire à la règle Application ; pas un update/delete générique)
3. Application `AssignPartyAccountRole` : Command + Handler ; lève
   `PartyAccountRoleAlreadyActiveException` si rôle déjà actif
4. Domain `PartyAccountRoleAssignment` **non modifié**
5. Tests Integration PostgreSQL : round-trip, revoke, doublon rejeté avant SQL
   (assert count=1 après exception), réassignation après revoke
6. `revoke()` repo : précondition documentée (entité gérée / findById même requête) ;
   pas encore de use case Application `RevokePartyAccountRole`
7. `AssignPartyAccountRoleHandler` = service invocable (`__invoke`), **pas**
   `#[AsMessageHandler]` — bus Messenger (ADR-003) à brancher plus tard

## Deptrac

Couche `Application` déjà autorisée → `SharedDomain` + `ModuleDomain` — pas
d’assouplissement nécessaire.

## Clôture

Validé — voir `2026-07-21-party-role-assignment-cloture.md` (outils : 28/132,
phpstan/deptrac/phpcpd OK).
