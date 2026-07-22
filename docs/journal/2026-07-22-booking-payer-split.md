# Journal — 2026-07-22 — BookingPayerSplit

## Contexte

`booking_payer_split` : répartition historisée du montant à payer entre
plusieurs payeurs (ex. amicale / employé). Pattern assign/revoke comme
`BookingSettlement`.

## Règle de plafond (confirmée utilisateur)

À l’assignation d’une **nouvelle** ligne active :

`SUM(amount des actifs) + nouveau montant <= booking.total_vente_amount`

- **Pas** d’exigence d’égalité stricte — une répartition peut rester
  incomplète temporairement.
- Égalité exacte (**somme = total**) : **acceptée** (cas limite testé).
- Dépassement d’**un centime** : rejet Application avant SQL
  (`BookingPayerSplitExceedsTotalException`).

Note : le COMMENT SQL de référence évoque encore une égalité SUM = total ;
la règle métier **confirmée** pour OsTravel est le plafond (≤), pas
l’égalité. Documenté ici pour éviter une régression de lecture du schéma.

## Distinction Settlement vs PayerSplit

| | Settlement | PayerSplit |
|---|---|---|
| Lit `Booking.totalVenteAmount` | Non | Oui (lecture seule) |
| Mute le Booking | Non | Non |
| Plafond Application | Non | Oui (SUM actifs) |

## Devise

Colonne `currency_code` absente du schéma → montant en centimes devise
vente du booking ; `hydrateCurrency()` en Repository (ADR-003 DBAL sur
`vente_currency_code`). Mismatch → `BookingPayerSplitCurrencyMismatchException`.

## Index unique

`uq_booking_payer_split_active (booking_id, payer_account_id)` →
`hasActivePayerSplit` DBAL + `BookingPayerSplitAlreadyActiveException`
(même discipline que settlement / rôles Party).

### Test ajouté a posteriori (distinct du plafond)

`test_second_active_split_same_payer_is_rejected_before_sql` :

- Même `payer_account_id`, 2ᵉ assign actif sur le même booking.
- Montants choisis pour que le **plafond soit OK** (10k+10k ≤ 100k) —
  seul le doublon actif bloque (`already_active`), pas `exceeds_total`.
- Justifie le garde-fou Application miroir de l’index unique, indépendant
  de la règle de somme vs `total_vente_amount`.

## Nettoyage getters

Getters simplifiés (`return $this->x`) dans `BookingPayerSplit` uniquement.
Le trait `HistorizedBookingChildAccessors` a été introduit hors périmètre
puis **annulé** — cf. `2026-07-22-revert-historized-accessors-trait.md`.
Clone phpcpd Settlement ↔ PayerSplit **accepté** (todo.md).

## Qualité (clôture)

phpunit **304 tests / 1800 assertions** (dont le test hasActive ci-dessus) ;
phpstan OK ; deptrac 0 ; phpcpd : clone accepté documenté (voir todo).
