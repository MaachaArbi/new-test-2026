## Reprise à froid

Journal — HTTP pan financier Booking (charges, settlements, payer-splits).
**Date** : 2026-07-22
Trois POST uniquement (pas de GET individuel, pas d'update/delete) :
Pattern commun (réf. `AddBookingCancellationTierController` + `BookingHttpSupport`) :

## Origine

```
# TASK — HTTP : charges, settlement, payer_split

## Lecture obligatoire
1. AddBookingCancellationTierController.php (référence la plus proche :
   Handler avec vérifications multiples, gestion d'erreurs métier variées)
2. BookingHttpSupport.php (réutiliser tel quel)
3. Les 3 Handlers Application déjà faits et testés (AddBookingCharge,
   AssignBookingSettlement, AssignBookingPayerSplit) — ne rien changer
   dedans, juste les exposer

## Portée — 3 endpoints d'ajout, pas de GET individuel ni d'update/delete
1. POST /api/v1/bookings/{publicId}/charges
2. POST /api/v1/bookings/{publicId}/settlements
3. POST /api/v1/bookings/{publicId}/payer-splits

Pour chacun : DTO requête (validation Symfony sur l'input pur, reprendre
exactement les paramètres du Command Application existant, aucun champ
nouveau), Controller (résout publicId→booking, délègue, 201 Created),
DTO réponse (champs métier, PAS d'id interne — aucun de ces 3 agrégats
n'a besoin d'être re-référencé par un futur appel HTTP, contrairement à
la policy d'annulation).

## Nuances propres à chaque endpoint

### Charges
Money en input : accepter soit deux champs simples (achatAmountMinor: int,
achatCurrencyCode: string / venteAmountMinor: int, venteCurrencyCode:
string) — pas de structure imbriquée en entrée, cohérent avec la
simplicité du Command existant. metadata : accepter un objet JSON libre
(array), transmis tel quel au Command.

### Settlement
beneficiaryRole en input : string validée par Assert\Choice sur les
valeurs de BeneficiaryRole::cases() (même pattern que PenaltyType déjà
fait sur cancellation tier — PAS de ValueError PHP qui fuiterait en 500).
rate : string optionnelle, pas de validation de format stricte côté DTO
(le VO SettlementRate valide déjà, laisser remonter son exception si
invalide).

### Payer split
Aucune nuance particulière — Command simple (payerAccountId, amountMinor,
currencyCode).

## Erreurs — vérifier le mapping HTTP de CHAQUE exception métier de ces
   3 domaines dans ExceptionListener, corriger/compléter si absent :
- BookingChargeTravelerMismatchException / SegmentMismatchException /
  BookingUnknownChargeTypeException : 422 (cohérent avec les mismatches
  déjà classés ainsi ailleurs)
- BookingSettlementAlreadyActiveException : 409 (cohérent avec
  *AlreadyActive déjà établi)
- BookingPayerSplitAlreadyActiveException : 409
- BookingPayerSplitExceedsTotalException : 422 (erreur de contenu de la
  requête vis-à-vis d'un état existant, pas une vraie règle de transition
  comme les statuts — trancher et documenter si tu juges qu'un autre code
  est plus juste, ne pas laisser au défaut sans réflexion)
- BookingPayerSplitCurrencyMismatchException : 422

## Tests (WebTestCase, PostgreSQL réel), pour CHACUN des 3 endpoints
- Cas valide → 201, champs corrects, pas d'id interne
- Booking inexistant → 404
- Erreur métier propre à chaque domaine (au moins 2 cas différents par
  endpoint, ex pour settlement : doublon actif ET devise invalide)
- Input malformé → 422
- Sans JWT → 401

Pour payer-splits spécifiquement : test du dépassement de total via HTTP
(pas juste re-tester ce qui est déjà couvert en Application — vérifier
que l'erreur remonte bien jusqu'au bon code HTTP avec le bon contexte
dans la réponse JSON).

## Documentation
- docs/journal/2026-07-2X-booking-financial-http.md
- docs/STATUS.md : "Booking : HTTP complet sur tout le pan financier
  historisé (charges, settlements, payer-splits). Reste : payment
  (différé), B15-B18/C3 ADR-003 (différé)."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Vu le volume (3 endpoints × DTO +
Controller + tests), regroupe la remontée en plusieurs messages plutôt
que de résumer — je vérifierai chaque fichier comme d'habitude.
```

## Décisions prises

Décisions attribuées :
- Mandat de décision délégué par le prompt d'origine (Cursor — à valider)

---

# Journal — HTTP pan financier Booking (charges, settlements, payer-splits)

**Date** : 2026-07-22

## Livré

Trois POST uniquement (pas de GET individuel, pas d'update/delete) :

| Endpoint | Handler Application (inchangé) |
|---|---|
| `POST /api/v1/bookings/{publicId}/charges` | `AddBookingChargeHandler` |
| `POST /api/v1/bookings/{publicId}/settlements` | `AssignBookingSettlementHandler` |
| `POST /api/v1/bookings/{publicId}/payer-splits` | `AssignBookingPayerSplitHandler` |

Pattern commun (réf. `AddBookingCancellationTierController` + `BookingHttpSupport`) :
resolve `publicId` → DTO validé Symfony → Command → **201 Created**, réponse métier **sans id interne** ni `bookingId` (ces agrégats ne sont pas re-référencés par un futur appel HTTP, contrairement à la cancellation policy).

## Nuances input

- **Charges** : Money en champs plats (`achatAmountMinor` / `achatCurrencyCode`, idem vente) ; `metadata` = objet JSON libre (`array`).
- **Settlements** : `beneficiaryRole` via `Assert\Choice` sur `BeneficiaryRole::cases()` (évite ValueError → 500) ; `rate` string optionnelle, format laissé au VO `SettlementRate`.
- **Payer-splits** : miroir direct du Command.

## Mapping HTTP (`ExceptionListener`)

| errorCode | HTTP | Motif |
|---|---|---|
| `booking.unknown_charge_type` | 422 | déjà classé |
| `booking_charge.traveler_mismatch` / `segment_mismatch` | 422 | déjà classé |
| `booking_settlement.already_active` | **409** | cohérent `*AlreadyActive` |
| `booking_payer_split.already_active` | **409** | idem |
| `booking_payer_split.exceeds_total` | **422** | contenu requête vs état existant (plafond), **pas** une transition de statut — 409 réservé aux conflits d'état exclusif (doublon actif) |
| `booking_payer_split.currency_mismatch` | 422 | mismatch contenu |
| `booking_settlement.invalid_rate` / `invalid` | 422 | ajoutés |
| `money.invalid_currency_code` | 422 | devise rejetée par VO Money (ex. settlement « devise invalide ») |

## Tests

WebTestCase × 3 endpoints : 201 sans id, 404 booking, ≥2 erreurs métier, 422 malformé, 401 sans JWT.  
Payer-splits : dépassement de total vérifié jusqu'au JSON (`code` + `context` `already_allocated_minor` / `requested_minor` / `allowed_total_minor`).

## Hors scope

Revoke settlement / payer-split HTTP ; payment ; B15–B18 / C3 ADR-003.

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-financial-http-cloture.md`.
