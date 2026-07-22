# BOOTSTRAP-REPORT — OsTravel

Date : 2026-07-21  
Projet : `/home/ubuntu/ostravel/`

## Objectif

Bootstrap isolé d’un backend Symfony 7.2 + PostgreSQL 16 + Nginx/PHP-FPM via Docker Compose,
sans toucher à l’application existante (`front-web` / Next / Gunicorn / Redis / Nginx hôte).

## Commandes hors `/home/ubuntu/ostravel/` (exceptions autorisées)

### 1. Swap 2 Gio (filet anti-OOM)

```bash
free -h
swapon --show
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
# ajout fstab si absent :
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
swapon --show
```

Résultat : Swap 2,0 Gio actif (`/swapfile`), ligne présente dans `/etc/fstab`.

### 2. Installation Docker Engine + Compose (repo officiel)

```bash
sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
# /etc/apt/sources.list.d/docker.sources → Suites: resolute (Ubuntu 26.04)
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo docker run --rm hello-world
```

Versions installées : Docker 29.6.2, Compose v5.3.1.

### 3. Ajout de `ubuntu` au groupe `docker`

```bash
sudo usermod -aG docker ubuntu
```

Vérifié : `getent group docker` → `docker:x:986:ubuntu`.

### Autres effets machine (conséquence de Docker)

- Service `docker.service` / `containerd.service` enabled
- Images Docker tirées : `hello-world`, `postgres:16-alpine`, `nginx:1.27-alpine`, `php:8.4-fpm-bookworm`, `composer:2`
- Network Docker `ostravel_ostravel`, volume `ostravel_postgres_data`
- Containers : `ostravel-postgres-1`, `ostravel-php-1`, `ostravel-nginx-1`

**Non touché :** `/etc/nginx/`, `/home/ubuntu/apps/`, services nginx/gunicorn/redis existants, aucun `systemctl restart` de ces services.

## Vérification non-perturbation de l’app existante

PIDs **identiques** avant/après `docker compose up` :

| Port | Process | PID(s) |
|---|---|---|
| 80 | nginx | 99392 / 178364 / 178366 |
| 3000 | next-server | 183424 |
| 6379 | redis-server | 182299 |
| 8001 | gunicorn | 204571 / 204575 / 204576 |

Bindings OsTravel uniquement en localhost :

| Port | Bind |
|---|---|
| 5432 | `127.0.0.1:5432` (docker-proxy) |
| 8080 | `127.0.0.1:8080` (docker-proxy) |
| PHP-FPM 9000 | non publié sur l’hôte |

Aucun bind `0.0.0.0:5432` / `0.0.0.0:8080`.

## Livrables dans `/home/ubuntu/ostravel/`

- Symfony 7.2 skeleton (`App\` namespace)
- Docker Compose (`name`/`COMPOSE_PROJECT_NAME=ostravel`), mem_limits, `shared_buffers=128MB`
- Doctrine XML (`config/doctrine/`), migrations configurées **sans** fichiers SQL
- Structure `src/Modules`, `src/Shared/{Domain,Application,Infrastructure}`, tests Unit/Integration/Shared
- Qualité : phpstan niveau 9, php-cs-fixer PSR-12, deptrac, phpcpd (isolé dans `tools/phpcpd/`), phpunit
- `.env` / `.env.example` / `.env.test` / `README.md`

## Notes / écarts assumés

1. **PHP 8.4** dans l’image Docker (exigence « 8.3+ ») : PHPUnit 13 et le lock Composer nécessitent ≥ 8.4.
2. **Symfony 7.2** installé avec `--no-security-blocking` : Packagist bloque 7.2 pour advisories de sécu ; 7.2 était non négociable.
3. **`sebastian/phpcpd`** : la v2 dans le vendor principal est incompatible avec Symfony Console 7.2 (casse PHPUnit). Exécutable via vendor isolé `tools/phpcpd/` (phpcpd 6.0.3). Le package reste utilisable pour le seuil 5 lignes / 3 tokens.
4. **HTTP 404** sur `http://127.0.0.1:8080/` : normal (aucune route métier au bootstrap) — Symfony boot OK (plus de 500).
5. Connexion Doctrine vérifiée : `DBAL ok=1` via le container `php` → service `postgres`.

## Commandes utiles

```bash
cd /home/ubuntu/ostravel
docker compose ps
docker compose logs -f
curl -I http://127.0.0.1:8080/
```
