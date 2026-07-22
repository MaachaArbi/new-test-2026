# Modèle conceptuel — Cash Management (Caisses & Banques)

**Statut** : V1.0 — figé le 17 juillet 2026
**Dépend de** : Party V1.2 (`party_account_office`), Règlements V1.0 (`reglement_instrument`, `reglement_payment_method`)
**Documents associés** : `schema-cash-management-v1.sql`, `reglements-currency_code-fix.diff`
**Testé sur** : PostgreSQL 16 réel, scénarios synthétiques construits à partir du fonctionnement legacy décrit par l'utilisateur (aucune donnée de relevé bancaire réelle disponible à ce jour — voir Limites).

---

## Principe directeur

Deux journaux jumeaux append-only — les sessions de caisse et les comptes bancaires — reliés par le bordereau de remise et la transmission externe. Cash Management ne recalcule **jamais** un solde tiers : il consomme `reglement_payment_method` via la table compagnon `cash_payment_method_routing` pour savoir où router physiquement chaque pièce, sans aucun code en dur. La garde physique d'une pièce est **dérivée** (`cash_instrument_location`), jamais saisie manuellement.

Contrairement à Party/Booking/Règlements, ce module part d'une conception neuve : le legacy (`ost_c_sessioncaisse`, `ost_banque_*`) a servi de liste de fonctionnalités à confronter, jamais de gabarit structurel (voir `00-INDEX.md`, principe directeur du 16/07).

---

## Entités

| Table | Rôle |
|---|---|
| `cash_routing_type` | Référentiel : `caisse` / `banque_directe` / `transmission_externe` / `aucun` |
| `cash_payment_method_routing` | Extension 1-1 de `reglement_payment_method` (même pattern que `party_account_office`/`party_account`). Pilote 100% du routing, plus aucun code en dur |
| `cash_movement_type` | Référentiel des types de mouvement de caisse (jamais ENUM) |
| `cash_session` | La caisse EST la session (pas d'entité caisse persistante séparée) |
| `cash_movement` | Journal append-only des sessions, montant signé, devise par ligne |
| `cash_session_balance` | Snapshot O(sessions × devises), maintenu par trigger |
| `cash_session_count` | Comptage de clôture, un par devise |
| `cash_cash_allocation` | Pont de traçabilité FIFO pour l'espèce individuellement trackée |
| `cash_transfer` | Transfert entre deux sessions ouvertes (mécanisme unique) |
| `cash_conversion` | Change de devise en un seul geste au sein d'une session |
| `cash_instrument_location` | Localisation dérivée d'une pièce (session / dépôt / banque / transmission) |
| `cash_bank_account` | Compte bancaire, entité de premier rang |
| `cash_bank_account_office` | Jonction N-N symétrique compte ↔ bureaux |
| `cash_bank_transaction_type`, `cash_bank_transaction` | Journal append-only du compte bancaire |
| `cash_bank_balance` | Snapshot du solde bancaire |
| `cash_deposit_type`, `cash_deposit`, `cash_deposit_item` | Bordereau de remise (header/items) |
| `cash_external_transmission`, `cash_external_transmission_item` | Bordereau de transmission externe (bon de commande / prise en charge) |
| `cash_bank_statement`, `cash_bank_statement_line` | Le relevé bancaire, version de la banque, jamais fusionnée avec notre journal |
| `cash_reconciliation_match` | Rapprochement N-N à montants partiels, jamais bloquant |
| `cash_validator_assignment` | Rattachement simple caissier central ↔ bureau, interne à `cash_` |

---

## Décisions structurantes

### 1. La caisse est la session, sans continuité automatique
Confirmé sur données réelles (`ost_c_sessioncaisse` ne porte ni devise ni caisse persistante). Le fond de caisse ne se transmet **jamais** automatiquement d'une session à l'autre du même utilisateur — cohérent avec le principe legacy "enveloppe" : l'argent retourne au caissier central à chaque validation.

### 2. Mode de règlement 100% configurable, aucun code en dur
`cash_payment_method_routing` étend `reglement_payment_method` sans réouverture du schéma Règlements. Créer un nouveau mode = une ligne dans chaque table. Le moteur ne connaît aucun code (`E`, `C`, `AD`...) en dur — il lit `routing_type_code` et `instrument_tracking_mode`.

**Nuance actée** : `is_cash_like` (Règlements) répond à "transite physiquement par une caisse" ; `routing_type_code` (Cash Management) répond à "vers où, y compris sans caisse". Les deux peuvent diverger — cas concret : le virement (`V`) est `is_cash_like=false` côté Règlements mais `routing_type_code='banque_directe'` ici, car il doit être rapproché sur relevé sans jamais toucher une caisse.

### 3. Fongibilité configurable — `instrument_tracking_mode`
`individual` (la pièce garde son lien vers l'instrument/le client jusqu'au dépôt) vs `aggregate` (fondu en un seul montant, comportement legacy). Choisi par mode de règlement, pas par une refonte de caisse physique séparée (idée "caisse de dépense" de l'utilisateur, jamais approfondie côté legacy — résolue ici sans changement opérationnel).

### 4. Allocation FIFO — la fongibilité réapparaît à la sortie
Un encaissement individuellement tracké reste identifiable à l'entrée, mais l'argent redevient fongible à la sortie (un billet ne porte pas le nom du client). `cash_cash_allocation` répond à "cette sortie provient de quel encaissement ?" via une consommation FIFO automatique, **best-effort** : elle trace ce qu'elle peut, ne bloque jamais un décaissement légitimement financé par du pool libre (alimentation, transferts reçus). Le vrai garde-fou "fonds insuffisants" est porté par le solde de session (`cash_post_outflow`), pas par l'allocation elle-même — **bug trouvé et corrigé pendant les tests** (voir Historique).

### 5. Isolation stricte des sources — option rare, structurellement gratuite
`strict_source_isolation` (booléen sur `cash_payment_method_routing`) empêche l'espèce individuellement trackée de ce mode de financer un décaissement — seule une source libre (alimentation, transfert) le peut. Activé pour `E` dans ce déploiement (1 cas sur ~100 clients, cf. échange du 17/07). Cohérent avec ADR-004 (1 base = 1 client) : le flag est global au déploiement, pas besoin de le scoper par bureau.

### 6. Validation tout ou rien — solde à zéro par construction
`cash_validate_session()` déverse la totalité d'une session fermée vers la session du caissier central : un mouvement agrégé par devise pour le non-traçable, un mouvement par pièce pour le traçable (chèque/LCN/PC/espèce individuelle non consommée). **Invariant vérifié par test réel** : `cash_session_balance` de la session validée est exactement 0 sur toutes les devises après exécution.

### 7. L'écart de clôture est un mouvement, jamais une colonne
`cash_count_session_currency()` matérialise l'écart (compté − théorique) comme un `cash_movement` de type `ecart_cloture`, posté **avant** la fermeture. Jamais de correction silencieuse hors journal.

### 8. Correction post-clôture — jamais de réouverture de session
`cash_reverse_movement()` poste une écriture inverse datée dans une session **ouverte** (la courante), jamais dans l'ancienne. Résout structurellement le problème legacy "annulation sur caisse déjà clôturée". Garde-fou : un mouvement déjà utilisé comme source d'allocation ne peut pas être renversé directement (limite V1 assumée, voir Limites).

### 9. Retour d'instrument impayé — point d'entrée unique, routé par localisation dérivée
`cash_handle_instrument_return()` route la contre-écriture selon `cash_instrument_location` — pas de reconstruction manuelle. Trois chemins testés : encore en session (mouvement caisse), déjà en banque/déposé (transaction bancaire négative), transmis en externe (statut `disputed`). Résout le problème legacy "difficile de tracer un chèque retourné et son impact".

### 10. Rapprochement N-N partiel, jamais bloquant
`cash_reconciliation_match` autorise 1 ligne de relevé ↔ N transactions et inversement, montants partiels. Un écart assumé (frais bancaires, agios) se qualifie via une transaction dédiée créée a posteriori, pas via une contrainte de blocage.

### 11. Comptes bancaires — N-N symétrique, aucune unicité de devise
Corrige deux limites legacy : l'hypothèse "1 compte = 1 bureau" (corrigée le 16/07 par l'utilisateur) et la contrainte `UNIQUE(devise)` sur `ost_banque_compte_bancaires` (jamais challengée faute de volume — rejetée explicitement ici).

### 12. Conversion de devise — remplace le hack à 3 opérations
`cash_conversion` remplace le contournement legacy "achat devise + mouvement caisse + paiement fournisseur" (cas concret : client algérien réglant un fournisseur tunisien en TND depuis une caisse DZD) par un geste unique, traçable, à deux jambes dans la même session.

### 13. Transmission externe (bon de commande / prise en charge)
`routing_type_code='transmission_externe'` + `cash_external_transmission`/`_item` répondent au flux amicale → bon de commande → transmission → remboursement, non géré en legacy. Statut porté **par ligne**, pas par bordereau (une amicale peut régler certaines PC et pas d'autres). Regroupement dès la V1 (décision actée, pour éviter une restructuration ultérieure).

---

## Historique — bugs trouvés et corrigés pendant les tests (17/07)

Trois défauts trouvés en testant le scénario réel (20 clients → 50 000 TND espèces, paiement fournisseur 10 000, dépôt 40 000+libre) et un chèque non déposé :

1. **`currency_id` → `currency_code`** : `schema-reglements-v1.sql` référençait une colonne inexistante sur `ref_currency` (bug pré-existant, isolé au module Règlements, corrigé en 4+13 occurrences — voir `reglements-currency_code-fix.diff`).
2. **`cash_allocate_fifo` bloquait à tort** un décaissement financé par du pool libre suffisant, en exigeant une couverture à 100% par du stock individuellement traçable. Corrigé : allocation best-effort, garde-fou de solde déplacé sur `cash_post_outflow`.
3. **`cash_validate_session` tentait de rouvrir temporairement** une session `closed`, violant la contrainte de cycle de vie. Corrigé : `closed` reste inscriptible pour les écritures de validation ; seule `validated` est vraiment figée.

Tous les chemins suivants ont été rejoués avec succès après correction : encaissement individuel × 20, isolation stricte (blocage puis déblocage via alimentation libre), traçabilité préservée sur 100% des instruments non consommés, dépôt banque avec localisation dérivée, validation tout-ou-rien (chemin agrégé ET chemin par pièce), correction post-clôture (succès en session ouverte + blocage double-annulation + blocage négatif), retour d'instrument sur les 3 localisations, rapprochement N-N avec écart qualifié a posteriori.

---

## Limites V1 assumées

- **Traçabilité multi-saut** : un transfert entre deux sessions (`cash_transfer`) fait perdre la traçabilité individuelle côté receveur (crédit générique, non rattaché au client d'origine). Jugé hors scope par l'utilisateur (cas rarissime).
- **`cash_reverse_movement`** refuse de renverser un mouvement déjà (même partiellement) consommé/déposé/transmis — l'appelant doit défaire chaque consommation séparément d'abord. Pas de mécanique de correction en cascade automatique en V1.
- **`cash_instrument_location`** ne se met pas à jour lors d'une contre-passation d'un mouvement de validation individuel (edge case non rencontré en usage normal, identifié en test — la pièce reste affichée à sa dernière position connue même après retour). À surveiller si le cas se présente réellement.
- **Rapprochement bancaire testé uniquement sur données synthétiques** — `ost_com_operations_bancaires` jamais fournie. Les tables sont structurellement solides (testées : match simple, partiel, écart qualifié a posteriori) mais pas confrontées à un vrai format de relevé.
- **Routing par défaut à confirmer** pour `V`, `VE`, `RC`, `RI` (posés par déduction raisonnable, modifiables par simple `UPDATE` sans migration — voir seed dans `schema-cash-management-v1.sql`).

## Points ouverts

Voir `sujets-reportes.md` pour le suivi — rapprochement à reconfronter dès que des exports réels de relevé seront disponibles ; routing par défaut des 4 modes marqués à valider avec l'utilisateur au fil de l'usage.
