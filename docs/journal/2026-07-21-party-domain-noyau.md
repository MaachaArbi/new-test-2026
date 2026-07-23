## Reprise à froid

Journal — 2026-07-21 — Party Domain noyau.
Couche Domain Party uniquement + tests unitaires (pas Doctrine, pas Controller).
Créé sous `src/Modules/Party/Domain/` :
- `ValueObject/Email.php`, `ValueObject/PartyAccountNature.php`

## Origine

```
# TASK — Module Party : premier noyau Domain (aucun code métier n'existe encore)

## Lecture obligatoire avant d'écrire quoi que ce soit
1. reference/README.md
2. reference/backend-cadrage/01-backend-architecture-decisions.md (ADR-002, ADR-002bis,
   ADR-018, ADR-020 en particulier)
3. reference/conceptual-models/modele-conceptuel-party.md (les 15 décisions clés)
4. reference/schemas/schema-party-account-v1.sql (déjà importé en base, source de
   vérité sur les contraintes réelles)

Aucune règle métier ne doit être déduite ou improvisée si elle n'est pas dans ces
documents. En cas de doute, ARRÊTE-TOI et demande plutôt que de supposer.

## Portée de ce prompt (strictement limitée)
Uniquement la couche Domain + tests unitaires. PAS de mapping Doctrine, PAS de
Repository implémenté, PAS de Controller — vagues suivantes séparées.

## Fichiers à créer

src/Modules/Party/Domain/
├── ValueObject/
│   ├── Email.php                    — immuable, validation format (miroir de
│   │                                   ck_party_account_email_format, mais la vraie
│   │                                   validation métier vit ici, pas en DB),
│   │                                   comparaison insensible à la casse (cohérent
│   │                                   avec uq_party_account_email_active sur
│   │                                   lower(email))
│   └── PartyAccountNature.php       — enum PHP natif : Person | Organization
│
├── Entity/
│   └── PartyAccount.php             — agrégat racine. Constructeur privé.
│                                       Factories statiques createPerson(...) /
│                                       createOrganization(...).
│                                       createPerson() DOIT rejeter un
│                                       parentAccountId non-null (règle de la note
│                                       d'implémentation #3 du schéma : parent
│                                       uniquement pour nature=organization).
│                                       Méthodes : disable(), markAsProspect(),
│                                       markAsDisputed(), updateDisplayName().
│                                       PAS de collection roles/functions/groups
│                                       chargée sur l'agrégat (party_account est le
│                                       pivot le plus joint du système, cf. ADR-018 —
│                                       ne pas recréer le problème de performance que
│                                       BIGINT+public_id cherche à éviter).
│
├── Repository/
│   └── PartyAccountRepositoryInterface.php
│                                     — findById(int $id): ?PartyAccount,
│                                       save(PartyAccount $account): void.
│                                       PAS de delete() générique.
│
└── Exception/
    ├── InvalidEmailException.php
    └── InvalidPartyAccountStateException.php

tests/Unit/Modules/Party/Domain/
├── ValueObject/
│   └── EmailTest.php                — rejette format invalide, accepte format
│                                       valide, deux emails de casse différente sont
│                                       considérés égaux
└── Entity/
    └── PartyAccountTest.php         — createPerson() OK, createOrganization() OK,
                                        createPerson() avec parentAccountId renseigné
                                        lève InvalidPartyAccountStateException,
                                        disable() change bien l'état

## Contraintes non négociables
- Zéro dépendance Symfony/Doctrine/framework dans Domain/ (vérifiable via `deptrac
  analyse` déjà configuré — faire tourner deptrac à la fin et confirmer 0 violation)
- Zéro accès base de données dans ces tests (doivent tourner en < 0.1s au total)
- PHPStan niveau max sans erreur sur les fichiers créés
- phpcpd sans duplication détectée

## Documentation de fin de session
- docs/journal/2026-07-2X-party-domain-noyau.md
- docs/STATUS.md mis à jour (Party : Domain noyau en cours — ValueObjects + agrégat
  racine + repository interface, PAS encore les assignations rôle/fonction/groupe)
- docs/backlog/in-progress.md : lister explicitement ce qui reste pour Party (les 3
  agrégats d'assignation, CoreCredential, mapping Doctrine, migrations de données
  du compte agence)

Réponds avec le contenu des 7 fichiers créés, le résultat de `deptrac analyse`, et
le résultat des tests.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Party Domain noyau

## Demandé

Couche Domain Party uniquement + tests unitaires (pas Doctrine, pas Controller).

## Fait

Créé sous `src/Modules/Party/Domain/` :
- `ValueObject/Email.php`, `ValueObject/PartyAccountNature.php`
- `Entity/PartyAccount.php` (factories createPerson/createOrganization, disable, markAsProspect, markAsDisputed, updateDisplayName)
- `Repository/PartyAccountRepositoryInterface.php`
- `Exception/InvalidEmailException.php`, `Exception/InvalidPartyAccountStateException.php`

Tests : `tests/Unit/Modules/Party/Domain/...` — 7 tests, 17 assertions, ~5 ms.

## Vérifications

| Outil | Résultat |
|---|---|
| phpunit --testsuite Unit | OK (7 tests) |
| phpstan (Party) | OK 0 erreur |
| deptrac | **0 violation** (4 uncovered hors Domain Party) |
| phpcpd src/Modules/Party | No clones found |

## Hors projet

Aucune commande hors `/home/ubuntu/ostravel/`.
