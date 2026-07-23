## Reprise à froid

Journal — 2026-07-22 — Booking pivot : montants, devises, Money VO.
Vague montants/devises du pivot `booking` (hors `booking_charge` / `booking_settlement` — décisions conceptuelles #5/#6, hors périmètre). Colonnes : devises/taux achat·vente, totaux, marges, `paid_amount`,…
Vague montants/devises du pivot `booking` (hors `booking_charge` /
`booking_settlement` — décisions conceptuelles #5/#6, hors périmètre).

## Origine

```
# TASK — Booking pivot : montants, devises, Money VO partagé

## Lecture obligatoire
1. reference/schemas/schema-booking-v1.sql, colonnes money du pivot booking
   (achat/vente_currency_code, achat/vente_exchange_rate, total_achat_amount,
   total_vente_amount, marge_agence_amount, marge_distributeur_amount,
   paid_amount, payment_status)
2. reference/conceptual-models/modele-conceptuel-booking.md, section
   "Argent" et décision #5/#6 (booking_charge vs booking_settlement —
   HORS PÉRIUMÈTRE ici, juste pour contexte, ne pas les construire)
3. Le pattern OpenReferentialCode déjà utilisé 4 fois — payment_status
   N'EST PAS un cas similaire : c'est un CHECK SQL fixe ('unpaid',
   'partial', 'paid'), pas une table référentielle ouverte. Utiliser un
   enum PHP natif, comme CredentialProvider, pas OpenReferentialCode.

## 1. Money Value Object (Shared/Domain — nouvelle fondation, réutilisable)
src/Shared/Domain/ValueObject/Money.php
- Immuable : amount (int, unités mineures — centimes ou équivalent selon
  la devise), currencyCode (string, 3 caractères)
- fromMinorUnits(int $amount, string $currencyCode): self
- amount(): int, currencyCode(): string
- add(Money $other): self — lève une exception si les devises diffèrent
  (ne JAMAIS additionner deux devises différentes silencieusement)
- Volontairement PAS de résolution dynamique de minor_unit (ref_currency
  n'est pas encore modélisé côté Domain) — ce VO manipule des unités
  mineures brutes, la conversion affichage (centimes → unité) reste hors
  périmètre de ce prompt, à documenter explicitement comme tel dans le
  journal
- Exception dédiée : CurrencyMismatchException (Shared/Domain/Exception)

## 2. ExchangeRate Value Object (Shared/Domain)
src/Shared/Domain/ValueObject/ExchangeRate.php
- Stocke la valeur en STRING (pas float — précision NUMERIC(14,6), jamais
  de perte de précision par un float PHP), valide un format décimal
  raisonnable, toString()

## 3. PaymentStatus (Booking/Domain — enum PHP natif, pas OpenReferentialCode)
src/Modules/Booking/Domain/ValueObject/PaymentStatus.php
enum : Unpaid = 'unpaid', Partial = 'partial', Paid = 'paid'

## 4. Étendre Booking (Domain)
Ajouter à Booking.php : achatCurrencyCode/venteCurrencyCode (string simple,
pas de VO dédié — ref_currency non modélisé), achatExchangeRate/
venteExchangeRate (ExchangeRate), totalAchatAmount/totalVenteAmount/
margeAgenceAmount/margeDistributeurAmount (Money, cohérents avec leur
devise achat/vente respective), paidAmount (Money, devise vente),
paymentStatus (PaymentStatus, défaut Unpaid).

Étendre create() en conséquence (paramètres explicites, pas de valeur
implicite cachée — cohérent avec le NOT NULL du schéma). Mettre à jour
TOUS les appels existants à Booking::create() dans les tests déjà écrits
(BookingTest, BookingCompositePrimaryKeyProbeTest, BookingPersistenceTest)
pour fournir ces nouveaux paramètres — ne rien laisser casser.

## 5. Mapping Doctrine + Types
Money et ExchangeRate ont besoin de Doctrine Types custom (comme
PublicId/Email) — mais attention, Money porte DEUX informations (amount +
currency) alors qu'une colonne SQL n'en porte qu'une (ex.
total_achat_amount est juste un BIGINT, sa devise vient d'une AUTRE
colonne, achat_currency_code). NE PAS créer un Type Doctrine qui essaierait
de tout encoder dans une seule colonne — mapper amount et currencyCode
comme deux champs Doctrine séparés au niveau XML, et reconstruire l'objet
Money au niveau du Domain via une propriété calculée ou deux champs
internes qui alimentent le VO à l'hydratation. Réfléchis à la solution la
plus propre plutôt que de forcer un Type custom mal adapté — documente ton
choix dans le journal.

## 6. Migration
Retirer le DEFAULT 'TND' temporaire sur achat/vente_currency_code (le
Domain fournit maintenant toujours une valeur) — nouvelle migration
d'ajustement, pas une réécriture de la précédente.

## Tests
Unit : Money.add() avec même devise OK, devises différentes → exception ;
ExchangeRate accepte/rejette des formats ; PaymentStatus valeurs correctes
Integration : round-trip complet avec tous les nouveaux champs, vérifier
qu'aucune précision n'est perdue sur les montants/taux

## Documentation
- docs/journal/2026-07-2X-booking-money-fields.md — expliquer le choix de
  mapping Money (deux colonnes séparées, pas un Type composite)
- docs/STATUS.md : "Booking : pivot avec montants/devises complet. Reste :
  workflow, extensions par service, charges/settlements/traveler."
- docs/backlog/todo.md

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous
les fichiers créés/modifiés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-22 — Booking pivot : montants, devises, Money VO

## Contexte

Vague montants/devises du pivot `booking` (hors `booking_charge` /
`booking_settlement` — décisions conceptuelles #5/#6, hors périmètre).
Colonnes : devises/taux achat·vente, totaux, marges, `paid_amount`,
`payment_status`.

## Fondation Shared

- `Money` : unités mineures brutes (`int`) + code devise 3 lettres.
  **Pas** de résolution `ref_currency.minor_unit` — conversion affichage
  (centimes → unité majeure) hors périmètre de cette vague.
- `Money::add()` refuse les devises différentes (`CurrencyMismatchException`).
- `ExchangeRate` : string décimale (jamais `float`), format NUMERIC(14,6).
- Exceptions : `CurrencyMismatchException`, `InvalidCurrencyCodeException`,
  `InvalidExchangeRateException`.

## PaymentStatus

Enum PHP natif (`unpaid` / `partial` / `paid`) — CHECK SQL fermé, **pas**
`OpenReferentialCode` (contrairement aux codes référentiels ouverts).

## Mapping Money — choix retenu

Money porte **deux** infos (amount + currency) alors que le SQL les sépare
(`total_achat_amount` BIGINT + `achat_currency_code` VARCHAR partagé).

**Pas** de Type Doctrine composite (encoderait mal une colonne, ou
dupliquerait la devise sur chaque montant).

Retenu :

1. Propriétés Domain internes : `int` pour chaque montant + `string` pour
   `achatCurrencyCode` / `venteCurrencyCode`.
2. Mapping XML : colonnes séparées (`bigint` / `string` / `exchange_rate`).
3. Getters Domain `totalAchatAmount(): Money` etc. reconstruisent le VO
   à la lecture ; `create()` accepte `Money` et extrait `amount()` après
   contrôle de cohérence devise.

`ExchangeRate` reste un Type Doctrine 1:1 colonne (`exchange_rate`), comme
`Email` / `PublicId`.

## Migration

`Version20260722060000` : `DROP DEFAULT` sur `achat_currency_code` /
`vente_currency_code` (DEFAULT `'TND'` temporaire de la slice initiale
retiré — le Domain fournit toujours une valeur).

## Tests

- Unit : `MoneyTest`, `ExchangeRateTest`, `PaymentStatusTest`, `BookingTest`
- Integration : round-trip précision montants/taux (`BookingPersistenceTest`)

## Clôture

Vague **close et validée** — voir `2026-07-22-booking-money-fields-cloture.md`.
