## Reprise à froid

Journal — Liste paginée PartyAccounts (DBAL / ADR-003).
Date : 2026-07-21
- `GET /api/v1/party-accounts?page&limit&nature&search`
- `ListPartyAccountsQuery` / `Handler` / `Result` — SQL DBAL pur, COUNT séparé,

## Origine

```
# TASK — Endpoint de liste Party : GET paginé (DBAL direct, ADR-003)

## Lecture obligatoire
1. reference/backend-cadrage/01-backend-architecture-decisions.md, ADR-003
   (CQRS léger : Commands via ORM, Queries via DBAL SQL pur — les lectures ne
   doivent JAMAIS réhydrater l'agrégat Domain complet)
2. GetPartyAccountController.php (pattern déjà en place pour le format de
   réponse, mais CETTE fois on ne passe PAS par PartyAccountRepositoryInterface)

## Portée
GET /api/v1/party-accounts — liste paginée, avec filtres simples (nature,
recherche texte sur displayName). Aucune écriture. Premier endpoint du projet
qui suit vraiment le pattern Query DBAL plutôt que Repository Domain.

## 1. Query (Application, PAS de Repository Domain ici)
src/Modules/Party/Application/ListPartyAccounts/ListPartyAccountsQuery.php
  - page: int (défaut 1), limit: int (défaut 20, max 100 — imposer la borne
    haute explicitement, ne jamais laisser un client demander limit=100000)
  - nature: string|null (filtre optionnel)
  - search: string|null (recherche sur displayName, ILIKE)

src/Modules/Party/Application/ListPartyAccounts/ListPartyAccountsHandler.php
  - Utilise DBAL Connection directement (pas EntityManager, pas QueryBuilder
    ORM) — requête SQL explicite avec paramètres liés, jamais de
    concaténation de string pour les valeurs utilisateur
  - Retourne un petit DTO résultat : list<array> (lignes brutes déjà au bon
    format, pas d'objets Domain) + total count (pour la pagination) — PAS
    de réhydratation PartyAccount

## 2. Réponse HTTP
Format : {"data": [...], "meta": {"page": ..., "limit": ..., "total": ...,
"totalPages": ...}}. Chaque élément de data = même forme que
PartyAccountResponse (publicId, nature, displayName, email) — construite
directement depuis les colonnes SQL retournées, pas via PartyAccountResponse::
fromDomain() (qui prend un objet Domain qu'on n'a justement pas ici).

src/Modules/Party/Infrastructure/Http/Controller/ListPartyAccountsController.php
  - Query params : page, limit, nature, search
  - Valider page/limit manuellement (entiers positifs, limit borné) —
    mauvaise valeur → 422 cohérent avec le format déjà établi

## 3. Performance (le vrai sujet de cet endpoint)
- Vérifier qu'un index existe sur les colonnes filtrées (nature, display_name
  pour ILIKE) — si absent dans le schéma déjà importé, NE PAS modifier le
  schéma toi-même (il est figé côté BDD), documenter le besoin dans le
  journal pour remontée au chat DB architect
- Compter le total via une requête séparée mais légère (COUNT avec les mêmes
  filtres WHERE), pas via récupération de toutes les lignes en PHP

## Tests d'intégration (PostgreSQL réel)
- Liste sans filtre → structure correcte, pagination correcte sur un jeu de
  données connu (créer N comptes de test, vérifier total/totalPages)
- Filtre par nature → seuls les comptes de cette nature reviennent
- Recherche texte → correspondance partielle fonctionne
- limit demandé au-delà du max autorisé → plafonné ou 422 (choisir un
  comportement explicite, le documenter)
- page au-delà du total → liste vide, pas d'erreur
- Vérifier qu'AUCUN champ "id" interne n'apparaît nulle part dans la réponse
- Sans JWT → 401

## Documentation
- docs/journal/2026-07-2X-list-party-accounts-endpoint.md — inclure
  explicitement si un index a semblé nécessaire et absent
- docs/STATUS.md : "CRUD Party de base complet (create, read, list). Premier
  front jetable peut maintenant consommer l'API."
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — Liste paginée PartyAccounts (DBAL / ADR-003)

Date : 2026-07-21

## Livré

- `GET /api/v1/party-accounts?page&limit&nature&search`
- `ListPartyAccountsQuery` / `Handler` / `Result` — SQL DBAL pur, COUNT séparé,
  pas de réhydratation Domain
- Réponse `{data, meta:{page,limit,total,totalPages}}` — jamais `id` interne
- `limit > 100` → **422** (pas de plafonnage silencieux)
- page hors total → `data: []`, meta cohérente
- Deptrac : Application autorisé à Doctrine pour Query handlers (ADR-003)

## Indexes (schéma déjà importé)

Présents, rien à ajouter côté BDD :

- `idx_party_account_nature` (nature, `deleted_at IS NULL`)
- `idx_party_account_display_name_trgm` (GIN `pg_trgm` sur `display_name`)
  — adapté à `ILIKE '%…%'`

Aucun index manquant à remonter au chat DB pour cet endpoint.

## Suite

Update/delete PartyAccount HTTP si besoin ; premier front jetable possible.

## Clôture

Rattrapage + résultats finaux des 4 outils :
`2026-07-21-list-party-accounts-endpoint-cloture.md`.

