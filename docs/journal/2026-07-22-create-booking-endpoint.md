## Reprise à froid

Journal — 2026-07-22 — Endpoint POST /api/v1/bookings.
Création HTTP Booking, calquée sur `CreatePartyAccountController` : validation Symfony d'input → 422 dans le Controller ; règles Domain → ExceptionListener. Réutilise `CreateBookingHandler` / `CreateBookingCommand`…
Création HTTP Booking, calquée sur `CreatePartyAccountController` :
validation Symfony d'input → 422 dans le Controller ; règles Domain →

## Origine

```
# TASK — Endpoint d'écriture Booking : création (POST)

## Lecture obligatoire
1. CreatePartyAccountController.php + CreatePartyAccountRequest.php
   (pattern déjà validé : validation Symfony pour l'input avant Domain,
   422 géré dans le Controller, PAS via ExceptionListener)
2. CreateBookingCommand.php existant (18 paramètres — la Command CLI/test
   déjà utilisée, à ne pas dupliquer, le DTO HTTP la construit)
3. Booking::create() (les règles Domain déjà en place : endDate>=startDate,
   devise cohérente par montant — restent la seule source de vérité,
   aucune validation métier redondante dans le DTO HTTP)

## Portée
Un seul endpoint : POST /api/v1/bookings. Reprend exactement les paramètres
de CreateBookingCommand existant, aucun champ nouveau, aucune valeur par
défaut inventée.

## 1. DTO de requête
src/Modules/Booking/Infrastructure/Http/Dto/CreateBookingRequest.php
Validation Symfony sur ce qui est vérifiable sans connaître le Domain :
- folderId, customerAccountId, officeAccountId : #[Assert\NotBlank],
  #[Assert\Type('integer')], #[Assert\Positive]
- supplierAccountId : #[Assert\Type('integer')] si fourni, nullable
- serviceTypeCode, statusCode, channelCode : #[Assert\NotBlank] (le
  Domain/référentiel valide le contenu réel, pas le DTO — cohérent avec
  OpenReferentialCode qui fait sa propre validation)
- startDate, endDate : #[Assert\Date] (format), endDate nullable
- achatCurrencyCode, venteCurrencyCode : #[Assert\Length(exactly: 3)]
- achatExchangeRate, venteExchangeRate : string, #[Assert\NotBlank]
  (le VO ExchangeRate valide le format réel)
- totalAchatAmount, totalVenteAmount, margeAgenceAmount,
  margeDistributeurAmount, paidAmount : #[Assert\Type('integer')],
  #[Assert\PositiveOrZero]
- paymentStatus : optionnel, si absent défaut 'unpaid' (cohérent avec
  PaymentStatus existant)

## 2. Controller
src/Modules/Booking/Infrastructure/Http/Controller/CreateBookingController.php
- Même structure que CreatePartyAccountController : parse JSON, valide
  via Validator, 422 avec violations si échec, sinon construit
  CreateBookingCommand et appelle CreateBookingHandler existant
- 201 Created, corps = BookingResponse (réutiliser le DTO déjà fait),
  header Location vers /api/v1/bookings/{publicId}

## Tests (WebTestCase, PostgreSQL réel)
- Création valide → 201, Location correct, corps cohérent, vérifiable
  ensuite via GET (comme le test croisé déjà fait sur Party)
- serviceTypeCode invalide (référentiel inconnu) → propagé comme
  DomainException du VO, PAS un 422 (cohérent : c'est une règle Domain,
  pas une validation d'input basique — vérifier quel code HTTP en résulte
  réellement via ExceptionListener et documenter)
- endDate < startDate → DomainException (400), pas 422
- folderId manquant/non entier → 422
- Sans JWT → 401

## Documentation
- docs/journal/2026-07-2X-create-booking-endpoint.md — documenter
  explicitement la frontière 422 (input malformé) vs 400 (règle Domain)
  telle qu'observée dans les tests, pas supposée
- docs/STATUS.md : "Booking : create + read HTTP. Reste : list, sous-
  ressources HTTP (extensions/voyageurs/annulation), pan financier."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Endpoint POST /api/v1/bookings

## Contexte

Création HTTP Booking, calquée sur `CreatePartyAccountController` :
validation Symfony d'input → 422 dans le Controller ; règles Domain →
ExceptionListener. Réutilise `CreateBookingHandler` / `CreateBookingCommand`
et `BookingResponse`.

## Diagnostic — cause du 500 (avant correction)

Reproduit via `CreateBookingHandler` + `serviceTypeCode=not_a_real_service`
(après VO OpenReferentialCode OK) :

1. `Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException`
2. ← `Doctrine\DBAL\Driver\PDO\Exception`
3. ← `PDOException`
4. SQLSTATE **23503** — `booking_service_type_code_fkey`  
   `Key (service_type_code)=(not_a_real_service) is not present in table "booking_service_type".`

Même trou pour `status_code`, `channel_code`, `achat_currency_code` /
`vente_currency_code` (FK vers `booking_status`, `booking_channel`,
`ref_currency`). `PaymentStatus` = enum PHP — hors scope.

## Correction

`BookingReferentialValidator` (Application, DBAL / ADR-003) appelé par
`CreateBookingHandler` **avant** `Booking::create()` :
service / status / channel / currencies.

**Exceptions Domain** — 4 classes séparées (convention projet
1-classe-1-`errorCode()` fixe) :
`BookingUnknownServiceTypeException`, `BookingUnknownStatusException`,
`BookingUnknownChannelException`, `BookingUnknownCurrencyException`.
Mappées **422** via `UNPROCESSABLE_ERROR_CODES` dans ExceptionListener
(erreur de forme d'input côté client).

Note : une première itération regroupait les 4 codes dans une seule
classe paramétrée (`BookingUnknownReferentialCodeException`) — choix
pragmatique qui cassait la convention dont dépendent le catalogue de
traduction et ExceptionListener. Alignement fait pour rester cohérent
avec le reste du projet.

## Frontière HTTP (après correction)

| Cas | HTTP | Mécanisme |
|---|---|---|
| Input malformé (`folderId` manquant / non entier) | **422** | Validator Symfony |
| Code référentiel inconnu (service/status/channel/currency) | **422** | exceptions `BookingUnknown*Exception` |
| `endDate` < `startDate` | **400** | `booking.invalid_dates` (règle métier) |
| Sans JWT | **401** | Security Lexik |

## Livré

- `CreateBookingRequest` + `CreateBookingController` (201 + Location)
- `JsonRequestSupport` (Shared)
- `BookingReferentialValidator` + 4 exceptions dédiées
- Tests WebTestCase (201+GET, 422 référentiels ×5, 400 dates, 422 input, 401)
