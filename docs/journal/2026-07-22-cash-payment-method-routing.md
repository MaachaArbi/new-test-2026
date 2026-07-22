# Journal — 2026-07-22 — Cash Management : référentiel routing

## Portée

`cash_routing_type` + `cash_payment_method_routing` uniquement.
Hors vague : `cash_session`, `cash_movement`, fonctions PL/pgSQL.

## Schéma

Le fichier `reference/schemas/schema-cash-management-v1.sql` **n'était pas
versionné** dans le dépôt. Slice reconstruite d'après
`modele-conceptuel-cash-management.md` (décision #2) + contrainte
`chk_routing_tracking_consistency` :
`routing_type_code='aucun' ⟺ instrument_tracking_mode='not_applicable'`.

Migration `Version20260722200000` : seed 4 `cash_routing_type`, structure
seule pour `cash_payment_method_routing` (pas de seed des modes E/C/V… —
dépend des `payment_method_id` ; **suite logique** documentée).

## Domain / Application

- `CashRoutingType` (lecture), `InstrumentTrackingMode` (enum PHP),
  `CashPaymentMethodRouting` (mutable, validation croisée create/update)
- Create / Update handlers ; `create()` / `update()` distincts sur le repo
- `ReglementPaymentMethodRepositoryInterface::findById` ajouté

## Tests

Unit Domain + Integration PostgreSQL : rejet avant SQL des deux sens de
la contrainte, round-trip, update incohérent, doublon PK.

## Qualité

- phpstan : OK
- deptrac : 0 violations
- phpunit : 376 tests, 2482 assertions (2 notices préexistants)
- phpcpd : clone HttpSupport Booking↔Règlements accepté (todo) — aucun nouveau clone Cash
