# Journal — 2026-07-22 — Booking pivot : mutations workflow

## Contexte

Premier jeu de mutations post-création sur l'agrégat `Booking` :
`is_on_request`, assignation agent, `is_locked`, `is_disputed`,
`supplier_status_label`.

Hors périmètre : transitions `status_code` ; table `booking_on_request_flag`
(1-N raisons détaillées).

## Mapping XML

Les colonnes workflow **n'étaient pas** mappées (vagues précédentes =
identité + montants). Ajoutées dans `Booking.orm.xml` :
`is_on_request`, `assigned_agent_account_id`, `assigned_at`
(`datetimetz_immutable`), `is_locked`, `is_disputed`,
`supplier_status_label`.

## Décision Application — Handler unique

**Retenu : un seul `UpdateBookingWorkflowHandler` / Command** (champs
optionnels `has*` + valeur), aligné sur `UpdatePartyAccount` (PATCH
partiel).

Pourquoi pas N Handlers dédiés :

- Chaque action est une mutation de flag triviale (find → mutate → save) ;
  N Handlers dupliqueraient le boilerplate sans valeur métier.
- Un futur endpoint HTTP PATCH workflow se mappe naturellement sur une
  Command partielle.
- Le Domain garde des méthodes fines (`markAsOnRequest`, `assignToAgent`,
  etc.) — l'Application orchestre, elle n'écrase pas le modèle.

### `hasX=true` + valeur `null`

`hasX=true` + valeur=`null` (hors `hasAssignment` / `hasSupplierStatusLabel`,
qui traitent `null` comme une vraie valeur significative — unassign /
clear label) est traité comme « pas de changement demandé », pas comme
une erreur de requête malformée. Choix assumé pour cette vague — à
revisiter si un Controller HTTP futur doit distinguer « champ omis » de
« champ envoyé avec une valeur invalide ».

Couvert par `UpdateBookingWorkflowHandlerTest`
(`hasOnRequest=true` + `isOnRequest=null` → `booking.no_changes_provided`).

## Réassignation agent

`assignToAgent()` **remplace** une assignation existante sans erreur :
cas métier normal (file « en instance » / changement d'agent). Documenté
dans le Domain (docblock) et ici.

## Décision conceptuelle #3 — porte ouverte

`is_on_request` est **indépendant** de `status_code` (confirmé legacy).
Aucune contrainte croisée dans ce prompt ; `status_code` n'est même pas
mutable ici.

Une règle plus stricte plus tard (ex. interdire `is_on_request=true` si
`status_code='confirmed'`) reste possible en Domain — **non implémentée**,
à trancher explicitement si un besoin réel apparaît.

## Tests

- Unit : `BookingWorkflowTest` (chaque méthode + réassignation)
- Unit : `UpdateBookingWorkflowHandlerTest` (aucun has* / hasOnRequest+null → no_changes)
- Integration : `BookingWorkflowPersistenceTest` (persist + reload mapping)

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-workflow-cloture.md`.
