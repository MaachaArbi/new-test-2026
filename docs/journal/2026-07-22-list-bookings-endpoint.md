## Reprise à froid

Journal — GET /api/v1/bookings (liste paginée DBAL).
Endpoint de liste paginée des bookings, aligné sur le pattern Party (`ListPartyAccountsHandler` / Controller) : DBAL direct (ADR-003), jamais de réhydratation Domain, réponse `{data, meta}`.
Date : 2026-07-22
Endpoint de liste paginée des bookings, aligné sur le pattern Party

## Origine

```
# TASK — Endpoint de liste Booking : GET paginé (DBAL, partitionnement)

## Lecture obligatoire
1. ListPartyAccountsHandler.php + ListPartyAccountsController.php (pattern
   déjà validé — page/limit/filtres, DBAL direct, jamais de réhydratation
   Domain, format {data, meta})
2. Booking.php (les champs disponibles pour filtrage/affichage)
3. Rappel : booking est PARTITIONNÉE par booking_date (RANGE mensuel).
   Une requête qui filtre sur une plage de booking_date peut exploiter le
   partition pruning PostgreSQL (ne scanner que les partitions concernées) ;
   une requête sans filtre de date scanne toutes les partitions. Pas de
   changement de code nécessaire pour ça (PostgreSQL le fait automatiquement
   si booking_date apparaît dans le WHERE), mais EXPLIQUER ce point dans le
   journal et vérifier via EXPLAIN si le filtre par date déclenche bien le
   pruning en pratique (pas juste supposé).

## Portée
GET /api/v1/bookings — liste paginée avec filtres : folderId, customerAccountId,
serviceTypeCode, statusCode, et une plage de dates optionnelle
(bookingDateFrom/bookingDateTo, sur booking_date — le champ de partition).

## 1. Query (Application, DBAL — ADR-003)
src/Modules/Booking/Application/ListBookings/ListBookingsQuery.php
- page (défaut 1), limit (défaut 20, max 100 — même borne que Party)
- folderId, customerAccountId, serviceTypeCode, statusCode : tous ?int/?string
- bookingDateFrom, bookingDateTo : ?string (format Y-m-d)

ListBookingsHandler.php — construit le WHERE dynamiquement (même pattern
que ListPartyAccountsHandler : tableau de conditions + paramètres liés,
jamais de concaténation de valeur utilisateur), COUNT séparé pour la
pagination, SELECT explicite des colonnes nécessaires à l'affichage liste
(pas SELECT *, cohérent avec backend_antipatterns).

## 2. Réponse HTTP
Chaque élément de data : mêmes infos qu'un résumé (publicId, bookingDate,
serviceTypeCode, statusCode, customerAccountId, totalVenteAmount imbriqué
avec sa devise — pas tous les champs de BookingResponse, une liste n'a pas
besoin du détail complet, cohérent avec la distinction déjà faite côté
Party entre GET unitaire et liste).

## 3. Controller
Query params validés manuellement (même pattern que
ListPartyAccountsController : page/limit entiers positifs, bornes, 422 si
invalide), délègue au Handler.

## Tests (PostgreSQL réel)
- Liste sans filtre → structure paginée correcte
- Filtre par bookingDateFrom/bookingDateTo → seuls les bookings dans la
  plage reviennent (créer des bookings à des dates différentes, vérifier
  explicitement l'exclusion de ceux hors plage)
- Filtre par serviceTypeCode/statusCode/folderId/customerAccountId
- EXPLAIN (via requête SQL brute dans le test, PAS une assertion stricte
  bloquante si le format EXPLAIN varie, juste une trace informative dans
  le journal) sur une requête filtrée par date — documenter si le pruning
  se déclenche
- limit > max → 422
- Sans JWT → 401
- Vérifier zéro champ "id" interne

## Documentation
- docs/journal/2026-07-2X-list-bookings-endpoint.md — inclure
  explicitement l'observation EXPLAIN sur le partition pruning
- docs/STATUS.md : "Booking : CRUD HTTP de base complet (create, read,
  list). Reste : sous-ressources HTTP, pan financier."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — GET /api/v1/bookings (liste paginée DBAL)

Date : 2026-07-22

## Objectif

Endpoint de liste paginée des bookings, aligné sur le pattern Party
(`ListPartyAccountsHandler` / Controller) : DBAL direct (ADR-003), jamais de
réhydratation Domain, réponse `{data, meta}`.

## Filtres

| Query param | Colonne | Notes |
|---|---|---|
| `page` / `limit` | — | défauts 1 / 20, max limit 100 → 422 si dépassé |
| `folderId` | `folder_id` | int positif optionnel |
| `customerAccountId` | `customer_account_id` | int positif optionnel |
| `serviceTypeCode` | `service_type_code` | string optionnel |
| `statusCode` | `status_code` | string optionnel |
| `bookingDateFrom` / `bookingDateTo` | `booking_date` | Y-m-d, clé de partition RANGE |

## Forme d'un élément `data` (résumé liste)

Pas le détail complet de `BookingResponse` :

- `publicId`, `bookingDate`, `serviceTypeCode`, `statusCode`, `customerAccountId`
- `totalVenteAmount` : `{ amount, currencyCode }`
- zéro champ `id` interne

## Partition pruning PostgreSQL

La table `booking` est `PARTITION BY RANGE (booking_date)` (partitions mensuelles
`booking_y2026m07` … + `booking_default`).

- **Avec** `booking_date` dans le `WHERE` (filtres from/to) : PostgreSQL peut
  ne scanner que les partitions dont le RANGE chevauche la plage — *partition
  pruning*.
- **Sans** filtre de date : toutes les partitions sont candidates (scan plus
  large). Aucun changement de code applicatif n'est requis : le pruning est
  automatique dès que `booking_date` apparaît dans le prédicat.

### Observation EXPLAIN (test d'intégration)

Requête filtrée `booking_date >= '2026-08-01' AND booking_date <= '2026-08-31'`
exécutée via SQL brut dans `ListBookingsControllerTest` (trace STDERR, pas
d'assertion bloquante si le format EXPLAIN varie).

Plan observé (PostgreSQL 16, environnement test) :

```
Limit  (cost=10.31..10.32 rows=1 width=216)
  ->  Sort  (cost=10.31..10.32 rows=1 width=216)
        Sort Key: booking.booking_date, booking.id
        ->  Seq Scan on booking_y2026m08 booking  (cost=0.00..10.30 rows=1 width=216)
              Filter: ((booking_date >= '2026-08-01'::date) AND (booking_date <= '2026-08-31'::date))
```

- seule la partition enfant `booking_y2026m08` apparaît ;
- `booking_y2026m07` / `booking_y2026m09` absentes du plan → **pruning effectivement déclenché** (pas seulement supposé).

(Relancer `test_explain_date_filter_partition_pruning_trace` pour reproduire la trace.)

## Anti-phpcpd

- Bornes + méta `{page,limit,total,totalPages}` : `App\Shared\Application\ListPagination`
- Validation query `page`/`limit` : `App\Shared\Infrastructure\Http\ListPaginationRequestSupport`

## Fichiers

- `src/Shared/Application/ListPagination.php`
- `src/Shared/Infrastructure/Http/ListPaginationRequestSupport.php`
- `src/Modules/Booking/Application/ListBookings/ListBookingsQuery.php`
- `src/Modules/Booking/Application/ListBookings/ListBookingsHandler.php`
- `src/Modules/Booking/Application/ListBookings/ListBookingsResult.php`
- `src/Modules/Booking/Infrastructure/Http/Controller/ListBookingsController.php`
- `tests/Integration/Modules/Booking/Infrastructure/Http/ListBookingsControllerTest.php`
