# Modèle Conceptuel — Module Règlements Client/Fournisseur

**Statut** : Figé (V1.0) — 16 juillet 2026
**Remplace** : `ost_com_piece`, `ost_com_reglement`, `ost_com_impaye`, `ost_com_mode_reglement` et tout circuit de paiement legacy
**Convention de nommage** : préfixe `settlement_`, cohérent avec `party_`/`core_`/`ref_`/`booking_`
**Dépend de** : `party_` (tiers, comptes), `ref_` (devises), `booking_` (montants figés des réservations via `booking_payer_split`)
**Hors périmètre explicite** : Facturation/avoirs (référencés par id), Caisse/Banque (futur module Cash Management), double-entrée comptable

---

## Principe directeur

**Le départ est toujours une réservation** (l'équivalent d'une commande). Toute réservation a un prix d'achat et un prix de vente. Le module se divise verticalement en deux miroirs :

```
Vente  → Client     → Règlement → Factures / Avoirs
Achat  → Fournisseur → Règlement → Factures / Avoirs
```

**Théorème de convergence (critère de clôture d'une réservation) :**
`Total vente = Total règlements client = Total facturé client (lignes facture + avoirs)`
`Total achat  = Total règlements fournisseur = Total facturé fournisseur`

Ce théorème s'applique **réservation par réservation** (on décompose la facture au niveau de la ligne facture pour chercher ce qui concerne cette réservation). Il vaut pour une réservation annulée avec ou sans frais (les frais deviennent le nouveau total de référence avant facturation ; un avoir compense après facturation).

---

## Problèmes du legacy résolus

| Problème legacy | Cause | Solution dans ce module |
|---|---|---|
| Milliers d'autorisations de débit inutiles (91 % des pièces) | L'AD servait à exprimer "non payé", ce que le solde devrait exprimer seul | L'AD disparaît comme instrument. Un débit sans crédit en face = non payé, nativement |
| Incohérence solde fournisseur (changement fournisseur / montant modifié) | Total mutable (`montant_restant`) qui se désynchronise | Append-only : toute correction = contre-passation datée. Rien à désynchroniser |
| Deux calculs de solde qui divergent | "Mouvement" défini deux fois (réservations + pièces) | Une seule source de vérité : le grand livre. L'obligation y est projetée à la validation |
| Calcul de solde lourd, grand livre global catastrophique | Scan de tout l'historique à chaque appel | Snapshot `settlement_balance` maintenu incrémentalement. Tous les soldes = un scan O(comptes) |
| Pas de grand livre par devise | Notion récente, plaquée sur l'existant | Natif : la clé du livre est `(compte, rôle, devise)`. Une pièce DT ne rencontre jamais un livre EUR |
| Solde d'un mois variable dans le temps | Annulation d'aujourd'hui recalculée rétroactivement | `effective_date` distincte de `created_at`. L'annulation est datée d'aujourd'hui. Le passé ne bouge jamais |

---

## Décision centrale : grand livre append-only

**Modèle Amadeus / banquier.** Une écriture = un fait économique daté et immuable. Toute correction est une écriture nouvelle (contre-passation), jamais un UPDATE. Le solde = `SUM(amount_minor)` sur le livre du compte.

**Simple partie** (pas de double-entrée pour l'instant). Chaque écriture est typée pour que le futur module Cash Management puisse ajouter la contre-écriture (Caisse ↔ Client) sans rien réécrire.

**Toute correction = contre-passation + repost** (choix délibéré : −1000 puis +1200, pas un delta +200). Plus lisible sur un relevé, plus traçable en cas de litige.

---

## Entités

### Référentiels

| Table | Rôle |
|---|---|
| `settlement_payment_method` | Modes de règlement (table, jamais ENUM). `is_cash_like` = transit physique par caisse/banque, crochet pour Cash Management |
| `settlement_entry_type` | Nature des écritures avec `normal_sign` (+1 débit / −1 crédit). Extensible sans migration |

### Instrument — `settlement_instrument`

La **pièce** : instrument physique ou scriptural avec son cycle de vie propre. Ce n'est pas une écriture — une pièce *produit* des écritures (crédits) via le grand livre.

Colonnes clés :
- `party_account_id` + `party_role` : exactement un tiers porteur (client ou fournisseur). L'amicale et l'employé sont deux `party_account` distincts dans Party — plus de `amicale_id` fantôme.
- `currency_id` : natif à la pièce. Une pièce DT ne règle qu'un livre DT.
- `amount_minor` : montant nominal, **immuable**. Le "restant à allouer" est un dérivé (`amount_minor − SUM(allocations dans settlement_matching)`), jamais stocké.
- `instrument_ref` : référence externe (n° chèque, n° autorisation) — champ dédié, distinct du mode.
- `bank_name`, `due_date`, `issued_on`, `metadata` JSONB : métadonnées d'instrument, verbeux selon le type.
- `status_code` : `active` / `returned` / `cancelled`. Un retour de pièce génère une écriture inverse dans le grand livre, il ne mute pas le montant.

**L'autorisation de débit (AD) disparaît en tant qu'instrument.** Sa `DateEcheance` migre comme attribut `due_date` sur l'écriture d'obligation dans le grand livre.

### Grand livre — `settlement_ledger_entry`

**Cœur du module. Append-only garanti par trigger (UPDATE/DELETE rejetés en base).**

Clé de livre : `(party_account_id, party_role, currency_id)`. Un compte a autant de livres que de devises actives.

Colonnes clés :
- `amount_minor` signé : + débit (le tiers nous doit), − crédit (payé / on lui doit). Interdit à 0.
- `effective_date` : date comptable (effet économique). Distincte de `created_at` (saisie). Une annulation aujourd'hui d'une résa de mars porte `effective_date` = aujourd'hui.
- Origines typées (au moins une obligatoire) : `booking_id`, `instrument_id`, `invoice_id`, `credit_note_id`, `reverses_entry_id`, `transfer_id`.
- `reverses_entry_id` : auto-référence vers l'écriture contre-passée (traçabilité de la correction).
- `memo` : libellé lisible sur relevé.

**Types d'écritures et leur sens :**

| Code | Signe | Déclencheur |
|---|---|---|
| `obligation_vente` | +1 | Validation réservation (projetée depuis `booking_payer_split`) |
| `obligation_achat` | +1 | Rattachement fournisseur à une réservation validée |
| `reglement_client` | −1 | Pièce reçue du client |
| `reglement_fournisseur` | −1 | Pièce versée au fournisseur |
| `reversal` | ±1 | Contre-passation (signe opposé à l'écriture annulée) |
| `deposit` | −1 | Dépôt / avance B2B sans réservation en face |
| `remboursement_client` | +1 | Sortie caisse vers client (rembourse une avance) |
| `transfert_solde` | ±1 | Jambe d'un transfert inter-livres |

### Snapshot de solde — `settlement_balance`

PK : `(party_account_id, party_role, currency_id)`. Maintenu incrémentalement par trigger AFTER INSERT sur le grand livre. `balance_minor` = solde courant. Reconcilie toujours avec `SUM(amount_minor)` à froid (prouvé sur données réelles).

**C'est ce qui rend possible "grand livre de tous les clients" en une requête indexée O(comptes).** Sans ça, le calcul scanne tout l'historique — ton problème n°4, qualifié d'impératif.

### Lettrage — `settlement_matching`

Overlay N-N **optionnel** par-dessus le grand livre. **Ne touche pas le solde.**

Rattache une écriture de crédit (règlement) à une écriture de débit (obligation). Partiel autorisé (une pièce couvre partiellement une réservation). Défaisable soft (`unmatched_at`). `match_group` pour l'affichage visuel (lettre A, B...).

Supporte les deux modes coexistants :
- **Lettrage direct** (B2C, CB automatique) : `is_automatic = true`, match généré au moment de la saisie de la pièce.
- **Débit/crédit sans lettrage** (B2B en compte courant) : absence de lignes dans cette table. Le solde existe et est juste ; le lettrage est simplement omis.

### Transfert de solde — `settlement_transfer`

Entité à part entière : motif, auteur, date, montant. Deux `party_account` quelconques (pas de contrainte de parenté). Devise unique (les deux jambes sont dans la même devise). Partiel autorisé. Annulable en bloc via `reverses_transfer_id`.

**Atomicité garantie par `settlement_post_transfer()`** : la fonction crée le transfert + ses deux jambes dans une seule transaction. Impossible d'obtenir un demi-transfert. La FK `transfer_id` sur `settlement_ledger_entry` empêche toute jambe orpheline.

Cas d'usage primaire : report de dette employé → amicale quand le split initial n'est plus viable (employé parti, litige). Remplace le bricolage legacy "ristourne + fausse réservation diverse".

---

## Intégration avec les autres modules

### ← Booking (lecture uniquement, jamais d'écriture vers Booking)

| Information lue | Table Booking | Usage dans Règlements |
|---|---|---|
| Montant vente par payeur | `booking_payer_split` | Projeter l'obligation client (une écriture par ligne de split active) |
| Montant achat (impayé fournisseur) | `booking_settlement.amount_owed` | Projeter l'obligation fournisseur |
| Devise de la réservation | `booking.currency_*` | Vérifier la cohérence de devise de la pièce |

**Pattern de projection** : à la validation d'une réservation, l'application lit `booking_payer_split` (lignes `valid_to IS NULL`) et poste une écriture `obligation_vente` dans le livre de chaque payeur, du montant exact de sa ligne. Jamais de re-scan de Booking pour un solde ultérieur.

**`SolvencyCheckerInterface`** (stub Booking, retourne toujours `true`) : ce module en sera l'implémentation réelle. Il lira `settlement_balance` pour décider si un compte peut engager une nouvelle réservation.

### → Facturation (futur module)

Règlements référence `invoice_id` et `credit_note_id` par id. Jamais de structure de facturation dans ce module. La facture est l'autorité sur le montant facturé ; Règlements en lit le total pour vérifier le théorème de convergence.

### → Cash Management (futur module)

`settlement_payment_method.is_cash_like` est le crochet. À terme, chaque écriture de règlement avec `is_cash_like = true` génère une contre-écriture dans le livre de la caisse ou du compte bancaire concerné. La simple partie devient double-entrée sans rien réécrire.

---

## Décisions clés et justification

1. **Append-only garanti en base** (trigger), pas seulement par convention applicative. La correction la plus naturelle (UPDATE) est rendue impossible structurellement, pas juste déconseillée.

2. **`effective_date` ≠ `created_at`** — décision Amadeus / banquier. Le solde d'une période passée est stable par construction. L'annulation d'une résa de mars saisie en juillet porte `effective_date` en juillet.

3. **Contre-passation + repost, pas delta** — choix délibéré de lisibilité sur relevé. −1000 puis +1200 est plus clair pour un client B2B qu'un delta +200 sans contexte.

4. **L'obligation est projetée à la validation** depuis `booking_payer_split`, jamais recalculée depuis Booking. Source de vérité unique pour les soldes : le grand livre de Règlements. Booking reste source de vérité du montant de la réservation, consulté une seule fois.

5. **L'autorisation de débit (AD) disparaît** comme instrument (91 % du legacy). Un débit sans crédit *est* le non-payé. L'échéance portée par l'AD devient un attribut de l'obligation.

6. **`montant` et `montant_alloue` legacy ne migrent pas** — ce sont des dérivés calculables depuis le lettrage. Seul `montant_origine` (montant nominal de la pièce) migre dans `settlement_instrument.amount_minor`.

7. **Transfert de solde = entité à part entière** (option 2 choisie). Solidarité portée par le modèle (FK + fonction atomique), pas par une règle applicative à surveiller.

8. **Lettrage N-N optionnel** — le solde est juste avec ou sans lettrage. Les deux modes (lettrage direct B2C, débit/crédit B2B) coexistent dans la même structure sans séparation de code.

9. **Simple partie, pas double-entrée** — suffisant pour les soldes tiers. `is_cash_like` est le crochet pour la double-entrée future. Pas de plan comptable à construire maintenant.

10. **57 écarts legacy pièce↔règlement** — identifiés comme bugs, ignorés à la migration. Le modèle refuse les montants nuls dans le grand livre (`amount_minor <> 0`), ce qui force la migration à décider explicitement du traitement de chaque anomalie.

---

## Hors périmètre (reporté, cf. `sujets-reportes.md`)

- Facturation et avoirs (futur module, référencés par id depuis `settlement_ledger_entry`)
- Caisse et banque / Cash Management (futur module, crochet `is_cash_like` posé)
- Double-entrée comptable complète (crochet posé sur chaque écriture typée)
- Remboursement client en détail (mécanique connue : écriture `remboursement_client` + pièce sortante ; jamais conçu sur un cas réel volumineux)
