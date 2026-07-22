# Décision — sebastian/phpcpd incompatible avec Symfony Console 7.4

Date : 2026-07-21  
Contexte : reconstruction Symfony 7.4 LTS

## Instruction du prompt non suivie à la lettre

Réinstaller `sebastian/phpcpd` **et** disposer d’un PHPUnit exécutable. Les deux coexistent mal dans le même `vendor/` racine.

## Message d’erreur exact

Lors de `docker compose exec -T php vendor/bin/phpcpd --min-lines 5 --min-tokens 3 src/` :

```
Deprecated: ini_set(): Use of mbstring.internal_encoding is deprecated in /var/www/html/vendor/sebastian/phpcpd/phpcpd on line 47

Fatal error: Declaration of SebastianBergmann\PHPCPD\CLI\Application::doRun(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output) must be compatible with Symfony\Component\Console\Application::doRun(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output): int in /var/www/html/vendor/sebastian/phpcpd/src/CLI/Application.php on line 116
```

Lors de `docker compose exec -T php vendor/bin/phpunit` (même fatal) :

```
Fatal error: Declaration of SebastianBergmann\PHPCPD\CLI\Application::doRun(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output) must be compatible with Symfony\Component\Console\Application::doRun(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output): int in /var/www/html/vendor/sebastian/phpcpd/src/CLI/Application.php on line 116
```

## Cause

`sebastian/phpcpd` v2.0.1 (abandonné) déclare `"symfony/console": ">=2.2.0"` sans borne haute. Son `Application::doRun()` n’a pas le type de retour `: int` exigé par Symfony Console 7.4. L’autoload classmap charge cette classe et casse aussi PHPUnit.

## Alternatives envisagées

1. **Isoler phpcpd** dans `tools/phpcpd/` (vendor Composer séparé, phpcpd 6.x) et retirer `sebastian/phpcpd` du `require-dev` racine — approche déjà utilisée au bootstrap 7.2.
2. **Garder phpcpd dans le vendor racine** et accepter PHPUnit cassé (non acceptable).
3. **Remplacer phpcpd** par un autre détecteur de clones (hors scope du prompt).

## Décision proposée (en attente de validation)

Alternative **1** : `composer remove --dev sebastian/phpcpd` puis vendor isolé `tools/phpcpd/` + script Composer `phpcpd` pointant vers `tools/phpcpd/vendor/bin/phpcpd`.

**ARRÊT** — aucune de ces alternatives n’a été appliquée dans cette session. En attente de validation explicite.
