# Journal — 2026-07-21 — Corrections noyau Domain Party

## Contexte

Après la première livraison du noyau Domain Party, deux corrections ont été apportées puis validées. Aucun nouveau code dans cette clôture doc — récapitulatif uniquement.

## Corrections

### 1. `InvalidPartyAccountStateException.php` manquant

Fichier d’exception manquant à la première livraison ; fourni ensuite (`parentAccountNotAllowedForPerson()`, etc.). Validé.

### 2. Extraction `PublicId` vers Shared/Domain

- VO immuable `App\Shared\Domain\ValueObject\PublicId` : `generate()`, `fromString()`, `toString()`
- Anti-duplication anticipée : tous les futurs agrégats auront besoin d’un identifiant public (ADR-018)
- Package `ramsey/uuid` ^4.9 installé après vérification d’absence de conflit avec Symfony Console 7.4 (contrairement à l’incident phpcpd Composer)
- `PartyAccount` utilise `PublicId` au lieu d’une string UUID générée en interne
- Deptrac : couches `SharedDomain` / `ModuleDomain` ; règle `ModuleDomain` → `SharedDomain`

## Résultats finaux des 4 outils

**phpunit** (exit 0)
```
PHPUnit 13.2.4 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.23
Configuration: /var/www/html/phpunit.dist.xml

.......                                                             7 / 7 (100%)

Time: 00:00.021, Memory: 18.00 MB

OK (7 tests, 20 assertions)
```

**phpstan** (exit 0)
```
 [OK] No errors
```

**deptrac** (exit 0)
```
 -------------------- ----- 
  Report                    
 -------------------- ----- 
  Violations           0    
  Skipped violations   0    
  Uncovered            6    
  Allowed              3    
  Warnings             0    
  Errors               0    
 -------------------- ----- 
```

**phpcpd** (exit 0)
```
phpcpd 6.0.3 by Sebastian Bergmann.

Deprecated: SebastianBergmann\CliParser\Parser::parse(): Implicitly marking parameter $longOptions as nullable is deprecated, the explicit nullable type must be used instead in phar:///var/www/html/tools/phpcpd.phar/sebastian-cli-parser/Parser.php on line 44

No clones found.

Time: 00:00.001, Memory: 2.00 MB
```
