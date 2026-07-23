## Reprise à froid

Journal — 2026-07-21 — PartyAccountRoleAssignment (Domain).
Premier agrégat d'assignation Party : `party_account_role` (décision #11 — historisation via `valid_to`, pas d'UPDATE sur le contenu). Function et group suivront après validation.
Premier agrégat d'assignation Party : `party_account_role` (décision #11 —
historisation via `valid_to`, pas d'UPDATE sur le contenu). Function et group

## Origine

```
# TASK — Module Party : PartyAccountRoleAssignment (Domain uniquement)

## Lecture obligatoire
1. reference/schemas/schema-party-account-v1.sql (table party_account_role,
   party_role — noter que party_role est une TABLE référentielle, pas un ENUM
   figé, cf. commentaire dans le script)
2. reference/conceptual-models/modele-conceptuel-party.md, décision #11
   (historisation : jamais d'UPDATE sur le contenu, clôture via valid_to)
3. Le code déjà existant : src/Modules/Party/Domain/, src/Shared/Domain/
   (réutiliser PublicId si pertinent, ne pas le dupliquer)

## Contrainte explicite (piège à éviter)
party_role est un référentiel ouvert (nouveaux rôles ajoutables sans migration
de code — 'franchise' a été ajouté ainsi après coup). NE PAS modéliser le rôle
comme un enum PHP natif (contrairement à PartyAccountNature, qui lui est
réellement figé). Le rôle est un Value Object simple encapsulant une string
validée (format : non vide, cohérent avec VARCHAR(30) de la colonne role_code),
pas une liste fermée de cas.

## Portée
Domain uniquement. Un seul des trois agrégats d'assignation (rôle) — function et
group suivront une fois celui-ci validé, pas dans ce prompt.

## Fichiers à créer

src/Modules/Party/Domain/ValueObject/
└── PartyRoleCode.php              — VO immuable, string validée (non vide,
                                      longueur max cohérente avec la colonne),
                                      PAS un enum

src/Modules/Party/Domain/Entity/
└── PartyAccountRoleAssignment.php — agrégat représentant une ligne
                                      party_account_role. Factory statique
                                      assign(accountId, roleCode, createdBy):
                                      self (validFrom = now, validTo = null).
                                      Méthode revoke(): void — met à jour
                                      validTo à now (jamais de suppression,
                                      jamais de nouvelle instance : c'est un
                                      UPDATE de la ligne existante sur SA SEULE
                                      colonne de clôture, cohérent avec la
                                      décision #11 qui distingue "jamais
                                      d'UPDATE sur le contenu" et "clôture via
                                      valid_to"). isActive(): bool (validTo
                                      === null). Ne PAS gérer ici la prévention
                                      de doublon actif (nécessite une lecture
                                      base — sujet de la vague Infrastructure/
                                      Application suivante, pas de ce prompt).

src/Modules/Party/Domain/Repository/
└── PartyAccountRoleAssignmentRepositoryInterface.php
                                    — assign(PartyAccountRoleAssignment): void,
                                      revoke(PartyAccountRoleAssignment): void.
                                      PAS de delete(), PAS de update() générique
                                      (cohérent avec la discussion sur le soft
                                      delete non-uniforme).

src/Modules/Party/Domain/Exception/
└── (créer une exception si un cas d'erreur Domain existe réellement — sinon ne
    rien créer artificiellement ; PartyRoleCode::fromString('') doit lever une
    exception, réutiliser ou étendre DomainException comme les précédentes,
    avec context() et errorCode())

tests/Unit/Modules/Party/Domain/
├── ValueObject/PartyRoleCodeTest.php
└── Entity/PartyAccountRoleAssignmentTest.php
                                    — assign() crée une assignation active
                                      (isActive() true, validTo null), revoke()
                                      passe isActive() à false et fixe validTo,
                                      PartyRoleCode rejette une valeur vide

## Contraintes non négociables (inchangées)
Zéro dépendance framework, zéro accès DB dans ces tests, PHPStan niveau max,
zéro duplication détectée par phpcpd — porter une attention particulière à ne
pas dupliquer de logique déjà présente dans PartyAccount/PublicId/Email.

## Documentation
- docs/journal/2026-07-2X-party-role-assignment-domain.md
- docs/STATUS.md : Party — "assignation rôle : Domain fait, Infrastructure à
  venir ; function et group pas encore attaqués"
- docs/backlog/in-progress.md mis à jour

Colle le contenu de tous les fichiers créés, deptrac analyse, et les résultats
des tests.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — PartyAccountRoleAssignment (Domain)

## Contexte

Premier agrégat d'assignation Party : `party_account_role` (décision #11 —
historisation via `valid_to`, pas d'UPDATE sur le contenu). Function et group
suivront après validation.

## Piège évité

`party_role` est un référentiel **ouvert** (table, pas ENUM) — `PartyRoleCode`
est un VO string (non vide, max 30) **pas** un enum PHP (contrairement à
`PartyAccountNature`).

## Faits

- `PartyRoleCode`, `PartyAccountRoleAssignment` (`assign` / `revoke` / `isActive`)
- Repository : `assign` + `revoke` uniquement (pas de delete / update générique)
- Exceptions Domain : `InvalidPartyRoleCodeException`,
  `InvalidPartyAccountRoleAssignmentException` (double revoke)
- Pas de `PublicId` : la table `party_account_role` n'en a pas
- Pas de prévention de doublon actif (lecture base → Infra/Application)
