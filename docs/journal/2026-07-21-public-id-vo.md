## Reprise à froid

Journal — 2026-07-21 — Extraction PublicId (Shared Domain).
Suite du noyau Party Domain : remplacer la génération/stockage UUID string interne de `PartyAccount` par un VO partagé.
- Compatibilité `ramsey/uuid` ^4.9.3 vérifiée avant install : dépendances `php` + `brick/math` (+ `ramsey/collection` transitif) — **pas** de conflit Symfony Console (contrairement à l’incident phpcpd Composer).
- Créé `App\Shared\Domain\ValueObject\PublicId` : `generate()`, `fromString()`, `toString()` via Ramsey UUID v4.

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Extraction PublicId (Shared Domain)

## Contexte

Suite du noyau Party Domain : remplacer la génération/stockage UUID string interne de `PartyAccount` par un VO partagé.

## Faits

- Compatibilité `ramsey/uuid` ^4.9.3 vérifiée avant install : dépendances `php` + `brick/math` (+ `ramsey/collection` transitif) — **pas** de conflit Symfony Console (contrairement à l’incident phpcpd Composer).
- Créé `App\Shared\Domain\ValueObject\PublicId` : `generate()`, `fromString()`, `toString()` via Ramsey UUID v4.
- `PartyAccount` stocke / expose `PublicId` ; génération via `PublicId::generate()`.
- Deptrac : couches `SharedDomain` + `ModuleDomain` ; règle `ModuleDomain` → `SharedDomain`.
- Tests `PartyAccountTest` mis à jour (instance `PublicId`, validité UUID v4).

## Qualité

phpstan / deptrac / phpunit OK ; phpcpd OK après retrait d’un `equals()` non demandé qui dupliquait le squelette Email (seuil `--min-tokens 3`).
