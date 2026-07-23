## Reprise à froid

Journal — 2026-07-22 — Référentiel data-driven booking_service_type_extension.
Retour du chat DB architect (**Volet A**) : les listes PHP figées du type `ALLOWED_SERVICE_TYPES = ['flight','train',…]` ne doivent plus piloter quelles extensions (accommodation / transport_segment / car_rental)…
Retour du chat DB architect (**Volet A**) : les listes PHP figées du type
`ALLOWED_SERVICE_TYPES = ['flight','train',…]` ne doivent plus piloter

## Origine

```
# TASK — Appliquer le référentiel data-driven booking_service_type_extension

## Contexte
reference/schemas/schema-booking-v1.sql est à jour (re-synchronisé) : deux
nouvelles tables ajoutées par la conception BDD —
booking_service_extension (code, label) et booking_service_type_extension
(service_type_code, extension_code, N-N), seedées :
  accommodation     -> hotel
  transport_segment -> flight, train, maritime, transfer
  car_rental        -> car_rental
Objectif : remplacer la liste ALLOWED_SERVICE_TYPES codée en dur par une
lecture de ce référentiel.

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql (les deux nouvelles tables +
   leur seed exact)
2. AssertBookingServiceType.php existant (à transformer, pas dupliquer)
3. Les 3 Handlers qui l'utilisent : SetBookingAccommodationDetailHandler,
   AddBookingHotelRoomHandler, AddBookingTransportSegmentHandler

## Migration
Nouvelle migration slice : les deux tables + leur seed (3 lignes
extension, 6 lignes de mapping), fidèle au schéma de référence.

## Transformer AssertBookingServiceType
Nouvelle signature : __invoke(int $bookingId, string $extensionCode): void
- Charge le booking (comme avant, BookingNotFoundException si absent)
- Vérifie qu'une ligne (booking.serviceTypeCode, $extensionCode) existe
  dans booking_service_type_extension — requête DBAL directe (lecture,
  cohérent ADR-003), pas de nouvelle entité Domain nécessaire pour cette
  simple vérification d'existence
- Si absente, lève BookingServiceTypeMismatchException — adapter son
  contexte : remplacer expected_service_types (liste devinée côté code)
  par extension_code (string) + actual_service_type — plus juste
  maintenant que la source de vérité est en base, pas déduite par le code
  appelant

## Mettre à jour les 3 Handlers
- SetBookingAccommodationDetailHandler → ($bookingId, 'accommodation')
- AddBookingHotelRoomHandler → ($bookingId, 'accommodation')
- AddBookingTransportSegmentHandler → ($bookingId, 'transport_segment') ;
  SUPPRIMER la constante ALLOWED_SERVICE_TYPES, elle n'a plus lieu d'être

## Tests
Adapter les tests existants qui vérifient un rejet de service_type
mismatch — le contexte de l'exception change de forme, donc les
assertions sur context()['expected_service_types'] doivent être mises à
jour vers context()['extension_code']. Vérifier qu'aucun autre test ne
régresse.

Test obligatoire qui PROUVE le caractère data-driven (pas juste affirmé) :
dans un test dédié, insérer en SQL direct une nouvelle ligne
('bus', 'transport_segment') dans booking_service_type_extension, créer
un booking service_type='bus'... — ATTENDS, 'bus' n'existe pas encore
comme booking_service_type valide dans le schéma actuel (vérifie la liste
des services seedés). Si 'bus' n'est pas un service_type existant, utilise
plutôt un service_type déjà seedé mais PAS encore mappé à aucune extension
(vérifie lequel dans le schéma, ex: 'excursion' ou 'visa' si non mappés),
et prouve qu'il est rejeté par défaut, PUIS qu'ajouter la ligne SQL le
débloque sans toucher au code PHP. Documente précisément quel service_type
a été utilisé pour ce test et pourquoi.

## Documentation
- docs/journal/2026-07-22-booking-service-extension-data-driven.md —
  expliquer le changement de philosophie (référentiel pilote le
  comportement, plus de liste PHP figée), citer le retour du chat DB
  architect (Volet A)
- docs/STATUS.md, docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers modifiés (AssertBookingServiceType.php, les 3 Handlers,
l'exception, la migration) et le nouveau test data-driven, plus les
résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Référentiel data-driven booking_service_type_extension

## Contexte

Retour du chat DB architect (**Volet A**) : les listes PHP figées du type
`ALLOWED_SERVICE_TYPES = ['flight','train',…]` ne doivent plus piloter
quelles extensions (accommodation / transport_segment / car_rental) sont
autorisées pour un `service_type`. La source de vérité est un référentiel
N-N en base :

- `booking_service_extension` (code, label) — catalogue des extensions
- `booking_service_type_extension` (service_type_code, extension_code) —
  mapping autorisé

Seed initial : accommodation←hotel ; transport_segment←flight/train/
maritime/transfer ; car_rental←car_rental (3 + 6 lignes).

## Changement de philosophie

Avant : le Handler connaît la liste des `service_type` autorisés et la
passe à `AssertBookingServiceType`. Ajouter `bus` = redeploy PHP.

Après : le Handler ne connaît que le **code d'extension** qu'il implémente
(`accommodation`, `transport_segment`). L'Assert lit le mapping en DB
(DBAL, ADR-003, pas d'entité Domain pour une simple existence). Ajouter
`bus → transport_segment` = un INSERT SQL, zéro PHP.

## Livré

- Migration `Version20260722100000` (+ DDL aligné dans
  `reference/schemas/schema-booking-v1.sql`)
- `AssertBookingServiceType::__invoke(int $bookingId, string $extensionCode)`
- Exception : contexte `extension_code` + `actual_service_type` (plus
  `expected_service_types`)
- Handlers hôtel → `'accommodation'` ; transport → `'transport_segment'`
  (constante `ALLOWED_SERVICE_TYPES` supprimée)
- Test data-driven : service_type **`bus`** (seedé dans
  `booking_service_type` mais non mappé au seed initial) — rejeté, puis
  débloqué par INSERT SQL seul

## Prochaine action

car_rental_detail (pourra réutiliser Assert avec `'car_rental'`).
