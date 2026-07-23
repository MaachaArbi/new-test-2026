## Reprise à froid

Journal — 2026-07-21 — correction phpcpd PHAR.
Remplacer `sebastian/phpcpd` Composer (vendor racine) par le PHAR officiel autonome ;
ne pas utiliser l’approche vendor isolé `tools/phpcpd/`.
1. `composer remove --dev sebastian/phpcpd` — package absent du vendor/lock

## Origine

Origine : introuvable dans l'historique Cursor disponible

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — correction phpcpd PHAR

## Demandé

Remplacer `sebastian/phpcpd` Composer (vendor racine) par le PHAR officiel autonome ;
ne pas utiliser l’approche vendor isolé `tools/phpcpd/`.

## Fait

1. `composer remove --dev sebastian/phpcpd` — package absent du vendor/lock
2. `wget https://phar.phpunit.de/phpcpd.phar -O tools/phpcpd.phar` + `chmod +x`
3. Version : **phpcpd 6.0.3**
4. `.gitignore` : `tools/*.phar`
5. Script `tools/install-tools.sh` (idempotent)
6. README quick-start : `composer install` puis `./tools/install-tools.sh`
7. Vérifs : phpcpd scan exit 0 ; phpunit exit 0

## Hors `/home/ubuntu/ostravel/`

Aucune (wget depuis le projet ; docker compose exec uniquement).

## Doc

- `docs/decisions/2026-07-21-phpcpd-phar-correction.md`
- `docs/STATUS.md` mis à jour (phpcpd + phpunit OK)
