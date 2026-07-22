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
