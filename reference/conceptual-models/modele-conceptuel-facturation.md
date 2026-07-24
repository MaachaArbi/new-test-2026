# Modèle Conceptuel — Module Facturation / Avoirs

**Statut** : Cadrage validé (avant schéma SQL) — 18 juillet 2026
**Remplace** : `ost_com_facture`, `ost_com_facture_ligne`, `ost_com_facture_fournisseur`, `ost_com_facture_fournisseur_ligne`
**Convention de nommage** : préfixe `invoicing_`, cohérent avec `party_`/`core_`/`ref_`/`booking_`/`settlement_`/`cash_`/`sales_point_`
**Dépend de** : `party_` (tiers), `ref_` (devises, pays), `booking_` (`booking_payer_split`, `booking_settlement`, `booking_charge`), `settlement_` (crochets `invoice_id`/`credit_note_id`), `sales_point_` (FK reporting nullable)
**Hors périmètre explicite** : recalcul de solde (Règlements reste l'unique autorité), lien facture client ↔ facture fournisseur (cas GNV, voir `sujets-reportes.md`), FODEC côté vente (n'existe que côté fournisseur)

---

## Principe directeur

**Il n'existe qu'un seul grand livre : celui de Règlements.** Il s'ouvre à la validation de la réservation (`obligation_vente`/`obligation_achat`), indépendamment de toute facture. La Facturation n'est pas un deuxième circuit d'argent — c'est une **couche documentaire et fiscale** qui répond à une question différente de celle de Règlements :

- **Règlements** : la réservation est-elle payée, partiellement, pas du tout ?
- **Facturation** : la réservation a-t-elle été formalisée par un document fiscal, partiellement, pas du tout ?

Le théorème de convergence de Règlements (`Total vente = Total réglé = Total facturé`) n'est pas trois comptabilités à réconcilier — ce sont trois mesures du même fait économique. L'écart entre elles est l'information utile (payé mais pas facturé = alerte fiscale ; facturé mais pas payé = relance client).

**Règle absolue héritée de Règlements, jamais renégociée dans ce module** : Facturation ne recalcule jamais un solde. Une ligne facture **ancrée** à une réservation ne pose **aucune écriture nouvelle** dans le grand livre — elle documente une obligation déjà projetée à la validation de la réservation. Seules les lignes **libres** (sans réservation en face) déclenchent une écriture, parce qu'aucune obligation n'existe encore ailleurs pour ce montant.

---

## Deux origines de ligne, jamais deux mécanismes de facture différents

Une facture (client ou fournisseur) est un objet unique, dont les lignes peuvent avoir deux origines distinctes, potentiellement mélangées dans le même document :

### Ligne ancrée
Référence un fait déjà connu et immuable côté Booking :
- **Côté vente** : `booking_payer_split_id` — pas `booking_id`. Le split porte déjà le payeur exact et le montant exact ; c'est le niveau de granularité qui gère nativement le cas d'une réservation répartie entre plusieurs payeurs (ex. amicale + employé), chacun facturé séparément.
- **Côté achat** : `booking_settlement_id`, symétrique — le bénéficiaire fournisseur exact d'une réservation, avec son `amount_owed` propre. Cohérent avec le mécanisme de rapprochement fournisseur (voir plus bas).

Une ligne ancrée ne **crée** jamais d'écriture — elle documente. Le montant facturé sur un split/settlement est **cumulatif et plafonné** : `SUM(montants facturés sur ce split) ≤ montant du split`. Une réservation peut ainsi être répartie sur plusieurs factures dans le temps (facturation partielle successive), sans limite de nombre. **Appliqué en base par trigger** (`invoicing_check_invoice_line_split_cap`/`invoicing_check_supplier_invoice_line_settlement_cap`), pas seulement en discipline applicative — un bug de la première version du schéma laissait ce plafond non vérifié, corrigé le 18/07/2026 après détection en sandbox.

### Ligne libre
Désignation texte, montant saisi manuellement, taux de TVA choisi par l'utilisateur — aucune réservation derrière. C'est ce qui rend le système **universel** (facturation hors tourisme : prestation de conseil, frais divers, vente d'objet promotionnel côté vente ; loyer, imprimeur, électricité côté achat).

Une ligne libre **pose une écriture nouvelle** dans le grand livre au moment de la validation de la facture (`obligation_vente`/`obligation_achat`, origine `invoice_id`). C'est précisément le cas que `chk_entry_has_origin` avait anticipé en acceptant `invoice_id`/`credit_note_id` comme origine alternative à `booking_id`, avant même que ce module existe.

---

## Facturation fournisseur — deux mécanismes de saisie distincts

1. **Saisie libre** : facturation classique type logiciel de gestion commerciale généraliste (Dolibarr), sans réservation en face.
2. **Facturation par rapprochement** : l'utilisateur demande la création d'une facture/avoir fournisseur suite réservation ; le système lui présente la liste des `booking_settlement` dont les factures ne sont pas encore parvenues ou sont partiellement couvertes. Il sélectionne les réservations concernées et saisit le montant reçu sur la pièce physique. C'est un moyen de **rapprochement**, pas de calcul : ça garantit qu'aucune facture fournisseur n'est oubliée.

---

## Regroupement facture ↔ réservation(s)

- Une facture peut regrouper **N réservations**, sélection libre par l'utilisateur (par client, par période — jour/semaine/mois selon l'usage de l'agence). Aucune règle système imposée au-delà de la sélection manuelle.
- Une réservation (via un split ou un settlement précis) peut à l'inverse être **répartie sur plusieurs factures** dans le temps (facturation partielle successive). Cas rare en pratique mais structurellement supporté sans traitement spécial — c'est un sous-produit naturel du plafonnement cumulatif décrit plus haut, pas une fonctionnalité séparée.
- **Une facture = une seule devise.** Cohérent avec le grand livre, nativement scopé par `(compte, rôle, devise)`. Verrouillé dès la conception, pas une contrainte fiscale mais architecturale.

---

## Numérotation légale

- **Séquence globale unique de l'agence**, pas par bureau/point de vente (le legacy n'avait pas cette distinction, et rien n'impose de la construire pour l'instant).
- **Remise à zéro chaque année.** Aucun format d'affichage imposé légalement en Tunisie — seule la séquentialité stricte compte. Le format de présentation reste configurable côté application.
- **Seule la validation de la facture consomme un numéro** (pas le brouillon).
- **Aucun gap toléré**, y compris en cas d'échec technique en cours de transaction (contrainte plus stricte qu'une `SEQUENCE` PostgreSQL classique, qui peut sauter des valeurs sur rollback). Implique un verrouillage explicite du compteur dans la même transaction que l'INSERT de la facture (pattern déjà utilisé dans le projet pour `settlement_balance` via `SELECT ... FOR UPDATE`). À valider par un test de concurrence réel en sandbox avant de considérer le mécanisme fiable — le legacy n'a jamais révélé de problème, mais ça ne prouve pas l'absence de risque à un volume différent.
- **Séquence séparée pour les avoirs**, indépendante de celle des factures.
- **Annulation d'une facture validée** : avoir obligatoire, jamais de suppression ou d'annulation à blanc.

---

## TVA

- La vente d'une réservation est **toujours TTC**.
- Taux multiples selon pays (Tunisie : 0 %, 7 %, 19 % actuellement, mais le système doit supporter tous les pays — table de taux avec historique dans le temps, jamais codé en dur).
- **Deux formules de calcul possibles, choisies par ligne**, selon le service et le mode de vente (qui facture qui) :
  - **TVA sur le total** : l'agence facture l'intégralité du montant au client (cas où le client paie tout à l'agence).
  - **TVA sur la commission** : l'agence ne facture que sa commission, le fournisseur facturant directement le client pour le reste (taux souvent différent, ex. 19 % au lieu de 7 % en Tunisie).
- Le taux et la base de calcul retenus sont **stockés par ligne facture**, à titre informatif et pour recalcul, jamais recalculés dynamiquement depuis Booking après coup.

---

## Timbre fiscal

**Mécanique validée sur exemple concret (réservation de 1001 DT facturée en deux temps, sur deux factures) :**

- Le timbre est **collecté commercialement, pas fiscalement**, dès la réservation — logé comme un `booking_charge` de type `timbre` (extensible comme les autres types de charge, cohérent avec le principe "Booking constate un fait, il ne sait rien de la facturation"). Raison commerciale : un client B2C qui ne revoit jamais l'agence après sa réservation ne paierait jamais un timbre facturé séparément ; un client B2B refuserait un écart entre le montant annoncé à la réservation et celui de la facture.
- Le porteur du timbre est **par couple (facture, réservation)**, jamais global à la facture ni global à la réservation sur toute sa durée de vie. Si une réservation apparaît dans deux factures différentes, chaque facture déduit et remonte son propre 1 DT en en-tête, indépendamment de ce qui s'est passé sur l'autre facture — aucun état à traquer dans le temps.
- Dans une facture donnée, la **ligne porteuse** d'une réservation (la première ligne représentant cette réservation dans cette facture) déduit le timbre de sa base TVA avant application du taux, et le montant remonte en en-tête de facture (`total_timbre = SUM(1 ligne porteuse par réservation distincte représentée dans cette facture)`). Les autres lignes de la même réservation dans la même facture ne déduisent rien — leur 1 DT collecté en trop devient implicitement de la marge taxable (pas d'écriture, juste une absence de déduction).
- **Réassignation automatique** : si l'utilisateur supprime la ligne porteuse d'une réservation donnée, le système réassigne le rôle de porteuse à une autre ligne restante de **cette même réservation** dans la même facture (jamais à une ligne d'une autre réservation).
- **Exonération** : si le tiers est exonéré de timbre, aucune réservation le concernant ne porte de charge timbre — dès la source (Booking), pas seulement au moment de la facture. Aucune ligne ne devient porteuse, aucune déduction n'a lieu.
- Le montant du timbre (1 DT en Tunisie actuellement) est **variable dans le temps** — même logique qu'une table de taux avec historique, pas une constante codée en dur.

---

## FODEC

- Existe **uniquement côté facture fournisseur**.
- Comportement structurel proche de la TVA : **taux par ligne** (souvent 1 %), jamais un montant fixe par document comme le timbre. Aucune notion de ligne porteuse unique.

---

## Avoir — mécanique et déclencheurs

### Deux origines, deux régimes de création distincts (miroir de la distinction ligne ancrée/ligne libre)

- **Avoir sur ligne ancrée** : **jamais créé manuellement**. Uniquement généré automatiquement, déclenché depuis l'annulation d'une réservation côté Booking. Justification : c'est la zone la plus risquée à corriger à la main (timbre par couple facture/réservation, split figé, cohérence stricte avec le théorème de convergence de Règlements) — la fiabilité prime sur la flexibilité, cohérent avec le choix déjà fait dans le legacy.
- **Avoir sur ligne libre** : **manuel obligatoire**, seul moyen de corriger une facture sans réservation en face. Cas structurellement plus simple (pas de timbre par réservation, pas de split à préserver, pas de cohérence Règlements/Booking à recalculer), donc nettement moins risqué à fiabiliser que l'avoir ancré.

### Mécanique de l'avoir automatique (annulation Booking)

Exemple validé : réservation achetée 900 DT / vendue 1000 DT, facturée intégralement au client (facture de 1000). Le client annule partiellement, obtient un remboursement de 300 DT :
1. L'utilisateur saisit les frais d'annulation côté achat (ex. 600) et côté vente (ex. 700) directement depuis l'écran réservation.
2. Le nouveau `total_vente` de référence devient 700 (mécanique déjà actée côté Règlements/Booking, hors périmètre Facturation).
3. Puisqu'une facture de 1000 existait déjà, le système génère **automatiquement** un avoir client de 300, **partiel sur la ligne facture concernée** (pas nécessairement l'annulation de la ligne entière — un avoir peut porter un montant partiel sur une `facturation_invoice_line`).
4. Le total à payer côté réservation devient `1000 (facture) − 300 (avoir) = 700`, cohérent avec le nouveau fait.

**Le split facturé reste figé après facturation, jamais rouvert par un avoir.** Une fois qu'un montant a été facturé sur un split, cette capacité de facturation est définitivement consommée, que l'avoir vienne l'annuler ensuite ou non — l'avoir corrige un document fiscal déjà émis, il ne restitue jamais de droit à refacturer. Une réémission après avoir (facture erronée annulée puis vraie facture à refaire sur le même montant) doit être traitée comme une **exception documentée en dehors du flux normal**, pas comme un cas natif du modèle.

**Cas où aucune facture n'existe encore au moment de l'annulation** : aucun avoir généré. Seul le fait Booking est corrigé, et la facturation ultérieure se base directement sur le nouveau total (700 dans l'exemple). L'avoir n'existe que pour rattraper un document déjà émis, jamais en anticipation.

### Asymétrie fournisseur — effet miroir incomplet, assumé

Côté fournisseur, **aucun avoir n'est généré automatiquement**, même après une annulation avec frais fournisseur saisis. L'agence ne peut pas anticiper le document que le fournisseur va réellement émettre. À la place : un mécanisme de **détection d'incohérence**, extension naturelle du rapprochement déjà décrit — si une facture fournisseur arrive pour un montant qui ne correspond plus à l'obligation projetée après correction (ex. le fournisseur facture encore 900 alors qu'un avoir de 300 était attendu), le système alerte plutôt que de générer quoi que ce soit.

### Cas non résolu, volontairement non sur-ingénieré

Répartition d'un avoir automatique quand la réservation concernée a été facturée sur **plusieurs factures distinctes** : jamais rencontré en pratique par l'utilisateur. Choix par défaut retenu : **l'utilisateur défalque manuellement** sur quelle(s) facture(s) l'avoir s'applique, le système ne devine aucune règle de répartition automatique (ni proportionnelle, ni FIFO). À reconfronter au premier cas réel en production.

---

## Facture/avoir libre — portée universelle

Objectif explicite : système de facturation utilisable même hors tourisme (aucune réservation en face). Une facture peut contenir uniquement des lignes libres, uniquement des lignes ancrées, ou un mélange des deux. Ce n'est pas une deuxième table de factures parallèle — c'est le même objet, avec une origine différente par ligne.

---

## Point de vente

`facturation_invoice.sales_point_id` (et l'équivalent côté facture fournisseur si pertinent), FK nullable vers `sales_point`. Reporting uniquement (quel point de vente a émis quelle facture), sans dépendance sur la numérotation (globale, pas par point de vente) ni sur le branchement `sales_point_id` côté Booking (action différée, notée séparément dans le backlog Booking).

---

## Intégration avec les autres modules

### ← Booking (lecture uniquement, jamais d'écriture vers Booking)

| Information lue | Table Booking | Usage dans Facturation |
|---|---|---|
| Split de paiement client | `booking_payer_split` | Ancrage de la ligne facture vente, plafond de facturation cumulée |
| Bénéficiaire fournisseur | `booking_settlement` | Ancrage de la ligne facture fournisseur (achat), rapprochement |
| Timbre collecté | `booking_charge` (type `timbre`) | Base de calcul TVA de la ligne porteuse, remontée en en-tête |
| Annulation / frais d'annulation | Événement Booking (hors table précise à ce stade) | Déclencheur de l'avoir automatique |

### ↔ Règlements

Facturation ne recalcule jamais un solde — Règlements reste l'unique source de vérité. Les crochets `invoice_id`/`credit_note_id` de `settlement_ledger_entry` ne sont utilisés que dans deux cas :
- **Ligne libre** (vente ou achat) : la validation de la facture/avoir pose une écriture nouvelle avec `invoice_id`/`credit_note_id` comme origine (aucun `booking_id` disponible).
- **Avoir sur ligne ancrée** : la contre-passation générée par l'annulation Booking porte `credit_note_id` en plus de son origine `booking_id`/`reverses_entry_id`, pour tracer le document fiscal associé.

Une ligne ancrée simple (facturation "normale" d'une réservation déjà obligée dans le grand livre) ne touche **jamais** le grand livre — c'est une lecture pure, cohérent avec le principe déjà figé dans `modele-conceptuel-settlement.md`.

### ← Cash Management

`cash_external_transmission_item.accompanying_invoice_id` reste une pièce jointe documentaire sur un bon de commande/prise en charge transmis à une amicale — confirmé sans logique de solde, non modifié par ce module.

### ← Point de vente

FK nullable en en-tête, reporting uniquement, aucune dépendance bloquante.

---

## Décisions clés et justification

1. **Un seul grand livre, jamais deux comptabilités parallèles.** La tentation naturelle (compte tenu du décalage entre paiement-sur-réservation et facturation-groupée) aurait été de construire un deuxième système de suivi de solde côté Facturation. Refusé explicitement — Facturation est une couche documentaire par-dessus un fait déjà acté ailleurs.

2. **Ancrage au niveau du split/settlement, pas de la réservation.** Une réservation n'est pas l'unité de facturation pertinente dès qu'elle a plusieurs payeurs ou plusieurs bénéficiaires — le split/settlement l'est.

3. **Timbre porté par Booking (`booking_charge`), pas par Facturation.** Cohérent avec le principe déjà acté dans Booking ("strictement additif, compose le total") et avec la réalité commerciale (le timbre est collecté à la réservation, pas à la facture).

4. **Avoir ancré exclusivement automatique.** Choix délibéré de robustesse plutôt que de flexibilité, sur la zone la plus risquée du module (cohérence timbre/split/grand livre). Confirmé par l'expérience legacy de l'utilisateur, qui avait fait le même choix pour les mêmes raisons.

5. **Split figé après facturation, jamais rouvert par un avoir.** Un avoir corrige un document fiscal, pas une capacité de facturation. Simplifie radicalement le modèle au prix d'un cas de réémission à traiter en exception plutôt qu'en flux natif — accepté comme compromis raisonnable.

6. **Numérotation sans gap garanti en base, pas seulement par convention applicative** — cohérent avec le pattern déjà utilisé pour l'append-only de Règlements (contrainte structurelle plutôt que discipline organisationnelle).

7. **Facture/avoir libre pour l'universalité, mais cantonnés à un régime plus permissif que le cas ancré.** Le risque associé (pas de timbre par réservation, pas de split à préserver) justifie qu'on y autorise ce qu'on refuse structurellement côté ancré (création manuelle d'avoir).

---

## Points restés ouverts, reportés en `sujets-reportes.md`

- **Lien facture client ↔ facture fournisseur (cas GNV)** : système où le client est lui-même considéré comme fournisseur (billetterie maritime). Explicitement écarté du périmètre V1, à noter comme cas rare et complexe pour une éventuelle session dédiée future.
- **Répartition automatique d'un avoir sur plusieurs factures distinctes** : jamais rencontré en pratique, choix utilisateur par défaut retenu (pas de règle système). À reconfronter au premier cas réel.
- **Réémission après avoir sur split figé** : traitement en exception documentée, pas de mécanisme natif prévu en V1.
- **Concurrence sur le compteur de numérotation** : le legacy (`GetNumInvoice`) n'a jamais révélé de problème en pratique, mais le volume actuel ne prouve pas l'absence de risque. Verrouillage transactionnel à valider par test de concurrence réel en sandbox avant mise en production.
- **Taux de TVA/timbre/FODEC** : tables avec historique dans le temps, rejoint le sujet transverse déjà identifié (`sujets-reportes.md` #34, organisation des référentiels TVA/État).
