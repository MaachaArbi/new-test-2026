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
