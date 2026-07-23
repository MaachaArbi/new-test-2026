## Reprise à froid

Journal — 2026-07-22 — Booking pivot : mutations workflow.
Premier jeu de mutations post-création sur l'agrégat `Booking` : `is_on_request`, assignation agent, `is_locked`, `is_disputed`, `supplier_status_label`. Hors périmètre : transitions `status_code` ; table…
Premier jeu de mutations post-création sur l'agrégat `Booking` :
`is_on_request`, assignation agent, `is_locked`, `is_disputed`,

## Origine

```
# TASK — Booking pivot : workflow (mutations Domain)

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, colonnes workflow du pivot :
   is_on_request, assigned_agent_account_id, assigned_at, is_locked,
   is_disputed, supplier_status_label
2. reference/conceptual-models/modele-conceptuel-booking.md, décision #3
   (is_on_request indépendant de status_code — PAS de contrainte croisée à
   inventer dans ce prompt, juste noter la possibilité)
3. Booking.php existant (aucune méthode de mutation pour l'instant — ce
   prompt introduit le premier cas sur cet agrégat)

## Portée
Uniquement les mutations post-création du pivot. PAS de changement de
status_code lui-même (sujet à part — transitions de statut, probablement
plus complexe, vague future). PAS booking_on_request_flag (table 1-N des
raisons détaillées, séparée, vague future).

## Méthodes à ajouter à Booking.php
- markAsOnRequest(): void — pose is_on_request = true
- clearOnRequest(): void — repasse à false
- assignToAgent(int $agentAccountId): void — pose
  assigned_agent_account_id + assigned_at = now(). Remplace une assignation
  existante si déjà assignée (pas d'erreur, une réassignation est un cas
  normal — documenter ce choix dans le journal)
- unassign(): void — remet les deux à null (cas "en instance")
- lock(): void / unlock(): void — is_locked
- markAsDisputed(): void / clearDispute(): void — is_disputed
- updateSupplierStatusLabel(?string $label): void — champ libre, juste un
  setter simple (pas de validation Domain, c'est un vocabulaire externe
  non structurant selon le schéma)

Aucune contrainte croisée entre ces méthodes et status_code — le champ
status_code n'est même pas mutable dans ce prompt. Documenter explicitement
dans le journal que la décision #3 laisse la porte ouverte à une règle plus
stricte plus tard (ex: interdire is_on_request=true si status_code=
'confirmed'), non implémentée ici, à trancher explicitement si un besoin
réel apparaît.

## Application
Un Handler par action serait excessif pour de simples mutations de flag —
mais rester cohérent avec le pattern déjà établi (vérification/orchestration
en Application, pas juste appeler le Domain depuis un futur Controller
directement). Décide : soit un seul
UpdateBookingWorkflowHandler/Command générique portant plusieurs champs
optionnels (cohérent avec le PATCH partiel déjà fait sur PartyAccount), soit
plusieurs Handlers dédiés — choisis en argumentant dans le journal, pas de
sur-ingénierie ni de raccourci non justifié.

## Tests (Unit suffisant pour cette vague — mutations pures, pas de nouveau
   risque Infrastructure/mapping puisque ces colonnes existent déjà mappées
   depuis les vagues précédentes... VÉRIFIER qu'elles sont bien mappées
   dans Booking.orm.xml, sinon les ajouter)
- Chaque méthode testée isolément (état avant/après)
- assignToAgent() sur un Booking déjà assigné → réassigne sans erreur
- Round-trip Integration : au moins un test qui persiste après mutation et
  relit, pour confirmer que le mapping XML (à vérifier/étendre) fonctionne

## Documentation
- docs/journal/2026-07-2X-booking-workflow.md — inclure explicitement la
  décision Application (Handler unique vs multiples) et la note sur la
  décision #3 laissée ouverte
- docs/STATUS.md
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés/modifiés et les résultats.
```

## Décisions prises

Décisions attribuées :
- Mandat de décision délégué par le prompt d'origine (Cursor — à valider)

---

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
