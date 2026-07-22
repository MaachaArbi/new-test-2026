# Décision — phpcpd via PHAR autonome (pas Composer)

Date : 2026-07-21  
Remplace / complète : `docs/decisions/2026-07-21-phpcpd-symfony-console.md`

## Problème

`sebastian/phpcpd` v2.0.1 installé via Composer dans le vendor racine était incompatible
avec Symfony Console 7.4 (`Application::doRun()` sans type de retour `: int`), ce qui
cassait à la fois `phpcpd` et `phpunit`.

## Changement de stratégie

| Avant (écarté) | Après (retenu) |
|---|---|
| Vendor Composer isolé dans `tools/phpcpd/` | PHAR officiel `tools/phpcpd.phar` |
| Second `composer.lock` à maintenir | Aucune dépendance Composer pour phpcpd |
| Risque résiduel de conflits de packages | Isolation totale du classpath |

## Pourquoi le PHAR

1. **Distribution officielle recommandée** par l’outil (`https://phar.phpunit.de/phpcpd.phar`)
2. **Aucun conflit de vendor** par construction (classpath autonome)
3. **Pas de second composer.lock** à synchroniser entre machines
4. Installation reproductible via `tools/install-tools.sh` (commité ; le `.phar` est gitignoré)

## Actions réalisées

- `composer remove --dev sebastian/phpcpd`
- `wget https://phar.phpunit.de/phpcpd.phar -O tools/phpcpd.phar`
- Version obtenue : **phpcpd 6.0.3**
- `tools/*.phar` dans `.gitignore`
- Script `tools/install-tools.sh` + mention dans le README quick-start

## Vérifications

- `php tools/phpcpd.phar --min-lines 5 --min-tokens 3 src/` → OK (exit 0)
- `vendor/bin/phpunit` → OK (exit 0, plus de fatal phpcpd)
