## Reprise à froid

Journal — 2026-07-22 — Cash Management : référentiel routing.
`cash_routing_type` + `cash_payment_method_routing` uniquement. Hors vague : `cash_session`, `cash_movement`, fonctions PL/pgSQL.
Le fichier `reference/schemas/schema-cash-management-v1.sql` **n'était pas versionné** dans le dépôt. Slice reconstruite d'après `modele-conceptuel-cash-management.md` (décision #2) + contrainte…

## Origine

```
# TASK — Module Cash Management : référentiel de routing (Domain + Application + Infrastructure)

## Lecture obligatoire
1. reference/schemas/schema-cash-management-v1.sql, tables
   cash_routing_type et cash_payment_method_routing (contrainte croisée
   chk_routing_tracking_consistency, trigger updated_at)
2. reference/conceptual-models/modele-conceptuel-cash-management.md,
   décision #2 (routing 100% configurable, aucun code en dur — même
   philosophie que le référentiel data-driven Booking qu'on a déjà
   corrigé)
3. ReglementPaymentMethodRepositoryInterface (existant, cash_payment_method_routing
   étend cette entité par son id)

## Portée
Uniquement le référentiel de routing. PAS cash_session, PAS cash_movement,
PAS les fonctions PL/pgSQL (cash_validate_session, etc.) — vagues futures
séparées, risque isolé volontairement comme pour le pivot Booking et le
grand livre Règlements.

## Domain

### CashRoutingType (référentiel simple, 4 valeurs seedées, table pas enum)
src/Modules/CashManagement/Domain/Entity/CashRoutingType.php
- code, label — lecture seule dans cette vague (le seed vit dans la
  migration SQL)

### InstrumentTrackingMode — enum PHP natif (CHECK SQL fixe, 3 valeurs)
src/Modules/CashManagement/Domain/ValueObject/InstrumentTrackingMode.php
enum : Individual = 'individual', Aggregate = 'aggregate',
NotApplicable = 'not_applicable'

### CashPaymentMethodRouting (extension 1-1, MUTABLE — pas append-only)
src/Modules/CashManagement/Domain/Entity/CashPaymentMethodRouting.php
- paymentMethodId (PK=FK, pas de generator, comme organization_identity),
  routingTypeCode (string simple, vérifié en Application via référentiel),
  instrumentTrackingMode (InstrumentTrackingMode), strictSourceIsolation
  (bool), requiresCustodyCheck (bool, défaut true), isActive (bool)
- create(...) : valide la contrainte croisée AVANT construction —
  routingTypeCode='aucun' ⟺ instrumentTrackingMode=NotApplicable (les
  deux sens, miroir exact de chk_routing_tracking_consistency). Lever une
  exception dédiée si incohérent
- update(routingTypeCode, instrumentTrackingMode, strictSourceIsolation,
  requiresCustodyCheck, isActive): void — MÊME validation croisée
  qu'à la création. C'est la première entité Cash Management mutable,
  cohérent avec l'intention explicite du schéma ("modifiable par simple
  UPDATE sans migration")

## Repository
CashRoutingTypeRepositoryInterface : findByCode (lecture)
CashPaymentMethodRoutingRepositoryInterface : findByPaymentMethodId,
create/update via UnitOfWork (deux méthodes distinctes, pas un save
générique — cohérent avec la discipline déjà établie de nommer
explicitement l'intention plutôt qu'un verbe générique)

## Application
CreateCashPaymentMethodRouting/{Command,Handler} : vérifie que
paymentMethodId existe (ReglementPaymentMethodRepositoryInterface),
vérifie routingTypeCode existe, vérifie qu'aucun routing n'existe déjà
pour ce paymentMethodId (PK=FK, donc un seul possible — erreur dédiée si
doublon)

UpdateCashPaymentMethodRouting/{Command,Handler} : charge l'existant,
appelle update(), un seul commit()

## Migration
Nouvelle migration slice : cash_routing_type + seed (4 lignes),
cash_payment_method_routing (structure seule, pas de seed dans cette
vague — le seed des 4 modes existants dépend de payment_method_id déjà
en base, à vérifier/faire dans un second temps si le prompt le permet
sans complexité excessive, sinon le documenter comme suite logique)

## Tests (PostgreSQL réel)
- Création avec routing_type='aucun' + tracking='individual' → rejetée
  avant SQL (contrainte croisée violée dans ce sens)
- Création avec routing_type='caisse' + tracking='not_applicable' →
  rejetée avant SQL (l'autre sens de la contrainte)
- Création valide → round-trip
- Update qui change routing_type sans ajuster tracking en cohérence →
  rejeté par la même validation
- Doublon sur le même payment_method_id → rejeté avant SQL

## Documentation
- docs/journal/2026-07-2X-cash-payment-method-routing.md
- docs/STATUS.md : nouvelle ligne module Cash Management — "Référentiel
  routing fait. Reste : cash_session/cash_movement (pivot), fonctions
  PL/pgSQL à appeler (validate/reverse/allocate...), banque, dépôts,
  rapprochement, HTTP."
- docs/backlog/todo.md

## Remontée
Le workflow Git fonctionne maintenant — pousse ce que tu produis sur la
branche main, donne-moi juste la LISTE des fichiers créés/modifiés (noms
seulement, pas le contenu), je les récupère moi-même via
raw.githubusercontent.com. Ne redonne le contenu en texte brut que si je
te le redemande explicitement après vérification.

Relance phpstan/deptrac/phpcpd/phpunit.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

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
