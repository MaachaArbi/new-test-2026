## Reprise à froid

Journal — 2026-07-21 — reconstruction Symfony 7.4.
- Remplacer Symfony 7.2 (EOL) par skeleton **7.4 LTS** sans flags de contournement
- Garder `docker-compose.yml`, `docker/`, `.env`
- Réinstaller packages (orm-pack, messenger, validator, phpunit, phpstan+bridges, php-cs-fixer, phpcpd, deptrac)

## Origine

```
# TASK — Reconstruction du skeleton Symfony (7.4 LTS) + mise en place de la documentation

CONTEXTE : le bootstrap précédent (voir /home/ubuntu/ostravel/BOOTSTRAP-REPORT.md)
a installé Symfony 7.2 par erreur — 7.2 est End-Of-Life depuis juillet 2025, la bonne
cible est Symfony 7.4 (LTS, support jusqu'à novembre 2029). Docker, le swap, et
l'isolation réseau sont déjà en place et corrects, ne pas y toucher.

## Partie 1 — Reconstruction du skeleton Symfony

Aucun code métier n'existe encore (juste le skeleton + config), donc :
1. Supprimer le contenu applicatif actuel de /home/ubuntu/ostravel/ (composer.json,
   src/, config/, etc.) EN GARDANT docker-compose.yml, docker/, .env — ces fichiers
   d'infrastructure restent valides et n'ont pas besoin d'être régénérés.
2. Réinstaller proprement : `composer create-project symfony/skeleton:"7.4.*" .`
   dans le container php (ou en local si Composer y est installé), sans AUCUN flag
   de contournement (`--no-security-blocking`, `--ignore-platform-reqs`, etc.).
3. Si une erreur survient à l'installation : rapporte le message d'erreur EXACT, mot
   pour mot (pas de reformulation/résumé), et ARRÊTE-TOI pour validation au lieu de
   choisir une version alternative de ton propre chef.
4. Une fois installé, vérifie `composer show symfony/framework-bundle` pour confirmer
   la version résolue exacte (doit être 7.4.x).
5. Réinstalle les packages déjà prévus dans le bootstrap précédent (orm-pack,
   messenger, validator, phpunit, phpstan + bridges, php-cs-fixer, phpcpd, deptrac),
   en vérifiant que chacun déclare une compatibilité avec Symfony ^7.4.
6. Recrée la structure de dossiers (src/Modules, src/Shared/{Domain,Application,
   Infrastructure}, tests/{Unit,Integration,Shared}) et les configs qualité (phpstan
   niveau 9, php-cs-fixer PSR-12, deptrac avec les règles de couches déjà définies,
   phpcpd) — mêmes règles que le bootstrap précédent, rien à changer là-dessus.
7. Vérifie que `docker compose up -d` redémarre proprement et que la connexion
   Doctrine fonctionne (127.0.0.1:5432), sans avoir touché à la partie Docker/réseau.

## Partie 2 — Mise en place de la documentation de suivi (nouveau, à faire une fois pour toutes)

Créer sous /home/ubuntu/ostravel/docs/ :

docs/
├── STATUS.md
├── journal/
├── decisions/
└── backlog/
    ├── in-progress.md
    └── todo.md

Règles à respecter pour CE prompt et TOUS les prompts futurs sur ce projet :

- STATUS.md : réécrit ENTIÈREMENT à chaque session (jamais d'accumulation), doit
  tenir sur un écran. Contient : version Symfony/PHP actuelle, état de chaque module
  (Party/Core/Booking/... — non commencé/en cours/terminé), dernière action effectuée,
  prochaine action prévue.
- journal/YYYY-MM-DD-<sujet>.md : un NOUVEAU fichier par session, jamais d'édition
  d'un fichier journal existant (append-only au niveau du dossier). Contient : ce qui
  était demandé, ce qui a été fait, chaque commande ayant touché à autre chose que
  /home/ubuntu/ostravel/, les résultats de vérification (ports, PIDs, versions).
- decisions/YYYY-MM-DD-<sujet>.md : créé CHAQUE FOIS qu'une instruction explicite
  d'un prompt n'a pas pu être suivie à la lettre. Contient le message d'erreur exact,
  les alternatives envisagées, la décision prise. La création de ce fichier DOIT
  s'accompagner d'un arrêt de l'exécution pour validation — jamais d'une continuation
  silencieuse sur une décision non prévue par le prompt.
- backlog/in-progress.md et todo.md : mis à jour en fin de session.
- Rien à la racine du projet sauf ce que Symfony/Composer exigent structurellement
  (composer.json, .env, README.md). Le README.md peut simplement pointer vers
  docs/STATUS.md pour le détail.

Déplacer le contenu de BOOTSTRAP-REPORT.md (racine) vers
docs/journal/2026-07-21-bootstrap-initial.md tel quel (garder trace de ce premier
essai, y compris l'épisode Symfony 7.2), puis créer
docs/journal/2026-07-21-reconstruction-symfony-7-4.md pour cette session-ci.
Supprimer BOOTSTRAP-REPORT.md de la racine après déplacement.

## Livrable final
Réponds-moi avec le contenu de docs/STATUS.md et, s'il existe, tout fichier créé
dans docs/decisions/.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — reconstruction Symfony 7.4

## Demandé

- Remplacer Symfony 7.2 (EOL) par skeleton **7.4 LTS** sans flags de contournement
- Garder `docker-compose.yml`, `docker/`, `.env`
- Réinstaller packages (orm-pack, messenger, validator, phpunit, phpstan+bridges, php-cs-fixer, phpcpd, deptrac)
- Recréer structure modules + configs qualité
- Vérifier `docker compose up` + Doctrine
- Mettre en place `docs/` (STATUS, journal, decisions, backlog)
- Déplacer BOOTSTRAP-REPORT → `docs/journal/2026-07-21-bootstrap-initial.md`

## Fait

1. Contenu applicatif effacé ; infra Docker + `.env` + `docs/` conservés
2. `composer create-project symfony/skeleton:"7.4.*"` **sans** `--no-security-blocking` → OK
3. `composer show symfony/framework-bundle` → **v7.4.14**
4. Packages réinstallés sans bypass → OK (advisories : aucune)
5. Structure `src/Modules`, `src/Shared/{Domain,Application,Infrastructure}`, tests Unit/Integration/Shared
6. Configs : Doctrine XML, phpstan 9, php-cs-fixer PSR-12, deptrac, phpunit.dist.xml
7. `docker compose up -d` → OK ; DBAL `ok=1` ; HTTP 404 (pas de routes métier)
8. PIDs app existante inchangés (nginx 99392/178364/178366, next 183424, redis 182299, gunicorn 204571/204575/204576)
9. Bindings ostravel : `127.0.0.1:5432`, `127.0.0.1:8080` uniquement

## Commandes hors `/home/ubuntu/ostravel/`

Aucune nouvelle (Docker/swap déjà en place). `sudo chown` / `sudo ss` / `sudo rm` outils locaux uniquement sur le projet ou lecture ports.

## Blocage

`sebastian/phpcpd` v2 casse phpcpd **et** phpunit (voir `docs/decisions/2026-07-21-phpcpd-symfony-console.md`). **Arrêt pour validation.**
