# Modèle conceptuel — Pricing (marges de vente)

**Statut** : ✅ Conception V1.1 figée (19/07/2026, extension modalité de paiement du même jour)
**Documents** : `schema-pricing-v1.sql`, `party-account-group-extension.diff` (Party), `ref-static-country-group-extension.diff` (ref_static), `diff-pricing-payment-modality.sql`
**Historique de session** : `sujets-reportes.md` §1 (origine), §4 (party_account_group, clôturé), §43 (modalité de paiement, clôturé par cette extension), §49-§54 (détail complet de la session de conception initiale)

---

## 1. Périmètre

Pricing est un **moteur de règles conditionnelles**, appliqué par-dessus un prix d'achat quel qu'il soit (peu importe sa source — legacy aujourd'hui, futur module Contracting plus tard). Trois natures de règles cohabitent dans le même noyau générique, jamais mélangées entre elles :

1. **Marge** (`margin`) — fixe le prix de vente en l'ajoutant au prix d'achat.
2. **Commission** (`commission`) — redistribue une marge déjà fixée vers un bénéficiaire tiers, sans changer le prix de vente.
3. **Modalité de paiement** (`payment_modality`) — détermine la répartition acompte/solde entre agence et fournisseur, et au nom de qui la facture hôtel est établie.

**Hors périmètre, explicitement et vérifié à plusieurs reprises** :
- Aucun prix d'achat n'est stocké, saisi ou calculé ici, à quelque granularité que ce soit — vérifié jusqu'au niveau le plus fin (une chambre, un jour, une cellule de future grille tarifaire).
- Le plafond/solvabilité par compte (`SolvencyCheckerInterface`) — nature différente (contrainte de solvabilité, pas une règle conditionnelle), reste un sujet Finance dédié (`sujets-reportes.md` §25bis).
- La segmentation commerciale fine (tags) — distincte du concept léger de "groupe d'affiliés" utilisé ici pour le ciblage (voir §3).
- L'impact texte sur les documents générés (voucher, contrat de voyage) — reste dans le module Documents (Permissions/Franchises/Configuration avancée), pas de FK ajoutée depuis Pricing.
- Le ciblage structurel B2B/B2C — délibérément écarté en session initiale, ciblage par compte/groupe suffit.

---

## 2. Noyau générique — `pricing_rule`

Toute règle, quelle que soit sa nature, porte un socle commun :

| Colonne | Rôle |
|---|---|
| `rule_nature_code` | `margin` / `commission` / `payment_modality` — discriminant, verrouillé côté table de détail par FK composite (voir §4) |
| `service_type_code` | Service concerné (réutilise `booking_service_type`, déjà figé) |
| `reservation_date_from`/`to` | Fenêtre de date de réservation, **universelle** (tout service), nullable = pas de contrainte |
| `is_active` | Désactivation temporaire sans suppression physique |
| `created_at`/`updated_at`/`created_by`/`updated_by` | Audit standard + résolution de conflit (voir §5) |

Les critères **propres à un service** (dates de séjour hôtel, pays de départ vol...) ne vivent JAMAIS sur `pricing_rule` — toujours dans une table compagnon dédiée au service (`pricing_rule_hotel_criteria`, `pricing_rule_flight_criteria`...). Principe anti-EAV appliqué strictement : jamais de table de critères générique, toujours une table typée par service, même au prix de répétition structurelle entre services.

---

## 3. Ciblage commun à tous les services

### Affilié
Compte précis (`pricing_rule_target_account`) et/ou groupe (`pricing_rule_target_group`), combinés en **OR**. Le groupe référence **`party_account_group`** (Party, pas une table propre à Pricing) — concept générique découvert en cours de session (voir `sujets-reportes.md` §4) : d'abord esquissé par erreur dans `pricing_`, puis relocalisé dès identification comme concept de portée générale (potentiellement réutile pour du reporting/statistiques). Dimensions de groupe superposables (`party_account_group_type`) — seule la dimension `commercial` est peuplée à ce stade.

### Source achat
`pricing_rule_purchase_source` — soit un `content_provider` réel (référentiel fermé, `ref_static`), soit un flag `is_local_direct` (contrat direct, futur Contracting), jamais les deux (CHECK d'exclusivité).

### Source vente
`pricing_rule_sale_channel` — réutilise `booking_channel`. **Point ouvert** : `booking_channel.api_in`/`api_out` porte une définition inversée par rapport à la terminologie confirmée par l'utilisateur ailleurs dans le projet — non corrigée à ce jour (`sujets-reportes.md` §49). Nationalité/IP/device explicitement écartés — absents du legacy, aucun besoin réel confirmé (§50).

---

## 4. Séparation stricte des natures — garde-fou structurel

Chaque nature a sa propre table de détail 1-1 avec `pricing_rule`, jamais fusionnées :

- `pricing_margin_detail`
- `pricing_commission_detail`
- `pricing_payment_modality_detail`

**Garde-fou anti-mélange** (bug trouvé et corrigé en sandbox, `sujets-reportes.md` §53, reconduit à l'identique pour la 3ᵉ nature) : chaque table de détail porte un `rule_nature_code` dénormalisé, verrouillé par `CHECK` à la valeur attendue, vérifié par une **FK composite** `(rule_id, rule_nature_code)` vers `pricing_rule(id, rule_nature_code)`. Empêche structurellement qu'une ligne de détail référence une règle d'une autre nature — pattern purement déclaratif, aucun trigger métier (conforme ADR-002). Testé trois fois en sandbox (marge/commission, puis marge/commission/modalité), aucune régression.

### Marge (`pricing_margin_detail`)
Pourcentage ou montant (`value_type_code`), peut être négatif (remise). Éclatement par type de passager (`value`/`value_child`/`value_infant`, une seule ligne par règle — pas d'éclatement en lignes, ensemble adulte/enfant/bébé fixe et fermé, cohérent avec l'anti-EAV) — confirmé nécessaire pour le vol, `value_child`/`value_infant` restent NULL pour les services non éclatés (hôtel). Une seule nature (%/montant) pour les 3 colonnes.

**Règle métier non enforced en base** : "montant ⇒ marge appliquée par chambre, source locale uniquement" (observée sur écran legacy hôtel) — cross-table avec `pricing_rule_purchase_source`, validée en Domain à la sauvegarde (ADR-002).

### Commission (`pricing_commission_detail`)
Même structure que la marge + `beneficiary_party_account_id` (qui touche la commission). Prélevée sur une marge déjà fixée, ne change jamais le prix de vente. Éclatement par passager construit par symétrie, **non confirmé** par l'utilisateur pour la commission spécifiquement.

### Modalité de paiement (`pricing_payment_modality_detail`) — ajouté 19/07, réouverture ciblée
Détermine, pour une combinaison de ciblage donnée (hôtel/chambre/affilié/dates — entièrement porté par le moteur générique, aucun ciblage propre) :
- `deposit_percentage` — % du total constituant l'acompte (le solde = 100 − acompte, pas de redondance stockée)
- `deposit_collector_code` / `balance_collector_code` — qui encaisse chaque jambe (`agency` ou `supplier`, jamais `client` — CHECK dédié)
- `invoiced_to_code` — au nom de qui la facture hôtel est établie (`client` ou `agency`, jamais `supplier` — ce serait une facture d'achat, hors périmètre Pricing)
- `label` — libellé de la modalité

Les trois colonnes de rôle référencent un petit référentiel dédié **`pricing_payment_party_role`** (`agency`/`supplier`/`client`) — pas `party_role` (Party), qui porte des rôles réels de tiers externes vis-à-vis d'un bureau, concept différent de cette bascule interne à la modalité de paiement.

---

## 5. Résolution de conflit — plusieurs règles matchent

Confirmé explicitement par l'utilisateur et testé en sandbox : **la règle la plus récemment créée qui matche l'emporte** (`created_at`, jamais `updated_at` — une simple correction de faute de frappe ne doit pas faire sauter une règle en priorité). Aucune notion de spécificité : **une règle générale créée après une règle ciblée écrase silencieusement cette dernière**, même pour le compte qu'elle ciblait précisément. Comportement confirmé voulu après démonstration concrète en sandbox, pas une simplification par défaut.

Logique de résolution entièrement en Domain (ADR-002), jamais en base :
1. Filtrer par `service_type_code` + `rule_nature_code` + `is_active=true` + fenêtre de dates.
2. Vérifier le OR sur affilié, le OR sur source achat, le OR sur canal vente, puis les critères propres au service.
3. Parmi les règles qui matchent tous les critères renseignés, prendre celle au `created_at` le plus récent.

---

## 6. Critères par service

| Service | Statut | Détail |
|---|---|---|
| **Hôtel** | ✅ Confirmé sur écran legacy réel | Checkin/séjour/durée séjour, hôtel précis et/ou chaîne (OR), chambre précise, arrangement précis, jours de semaine. Granularité vérifiée jusqu'à une seule chambre + un seul jour — condition posée pour une future grille tarifaire type booking.com (voir §8) |
| **Vol/Billetterie** | ✅ Confirmé sur écran legacy réel (2ᵉ écran, session étendue) | Pays départ/arrivée précis et/ou groupe de pays (OR, référence `ref_country_group` — voir §3bis ci-dessous), compagnie, classe cabine, date de départ (distincte de la réservation), intervalle de prix billet (devise obligatoire). Marge/commission toujours éclatée par passager (§4) |
| **Location voiture** | ✅ Confirmé après vérification explicite de Product/Catalogue | Durée de location, intervalle de prix + devise, modèle précis (`product_vehicle_model`) et/ou carrosserie (`product_vehicle_body_type`, le concept le plus proche d'une "catégorie" réellement disponible — pas de couche "catégorie de location" dans Catalogue), combinés en OR |
| **Transfert / Spa / Visa / Bus** | ⚠️ Improvisé | Aucun écran legacy vu, structure minimale par analogie, à reconfronter explicitement à la conception du futur module Contracting |
| **Maritime** | ❌ Non couvert | Aucune entité Product/Catalogue n'existe pour ce service (absent des 8 sous-modules figés) — une règle sur ce service ne peut utiliser que le noyau générique, aucun critère fin |

### 3bis. Groupement de pays (vol)
`ref_country_group`/`ref_country_group_member` — même relocalisation que `party_account_group` (§3), pour la même raison : géographie pure, sans dépendance légitime vers Pricing, réutilisable par d'autres modules référençant déjà `ref_country` (Visa notamment).

---

## 7. Audit trail — `pricing_rule_log`

Append-only, snapshot des champs modifiés (avant/après) à chaque création/modification/activation/désactivation/suppression, avec auteur — reproduit fidèlement la capture "Historique" du legacy (`event_type`/`field_changes JSONB`). `rule_id` en **FK applicative** (pas de contrainte réelle) : une règle peut être physiquement supprimée (pas de soft delete ici, cohérent avec le fait que Booking ne relit jamais les règles a posteriori — seul le résultat déjà résolu au moment de la vente compte), le log doit donc survivre à la suppression de la règle qu'il documente.

Pattern identique à `booking_log` — 3ᵉ occurrence réelle du besoin de log générique transverse (avec Booking et le futur Provider Integration), candidat à extraction non encore fait (`sujets-reportes.md` §44).

---

## 8. Lien avec la future grille tarifaire (hors périmètre, analysé)

L'utilisateur envisage un futur outil de type grille tarifaire (achat/marge/vente éditables par cellule, façon booking.com). Analyse actée en session : **pas de conception à part nécessaire**. Une cellule éditée dans cette future UI équivaut à une `pricing_rule` créée/ajustée avec des critères resserrés au maximum (une chambre, un arrangement, un jour) — condition de granularité fine explicitement vérifiée dans ce schéma pour cette raison précise. La colonne achat de cette grille lira le futur Contracting, jamais Pricing.

**Distinction importante découverte en session** : les captures legacy montrant achat et marge côte à côte dans le même formulaire ("Tarif Arrangements", "Politique Enfants", "Réductions Chambres") ne sont **pas** un oubli de périmètre — c'est une **deuxième famille de marge**, la micro-marge de contrat, structurellement rattachée au futur module Contracting (saisie au moment du contrat, sur des sous-lignes qui n'existent que dans son contexte), applicable **uniquement aux clients B2C**, jamais aux affiliés B2B. Voir `sujets-reportes.md` §52.

---

## 9. Décisions irréversibles actées

- Aucune table Pricing ne référence un prix d'achat, à aucune granularité, quelle que soit la source — vérifié explicitement à plusieurs reprises, y compris pour la modalité de paiement (qui ne détermine QUE la répartition et le nom de facturation, jamais un montant d'achat).
- EAV rejeté même pour un ensemble fixe et fermé de faible cardinalité (adulte/enfant/bébé) — 3 colonnes nommées préférées à un éclatement en lignes.
- Séparation stricte des natures de règle (margin/commission/payment_modality), jamais fusionnées, garde-fou structurel par FK composite reconduit à l'identique à chaque nouvelle nature.
- Un concept qui *semble* générique (regroupement, catégorie...) doit être vérifié contre les modules de couche plus basse (Party, ref_static) avant construction — pas après (leçon actée en session, cf. `00-INDEX.md`, méthode de conception point 6).

---

## 10. Points de couplage avec les autres modules

- `party_account_group` (Party) → ciblage affilié
- `ref_country_group` (ref_static) → ciblage géographique vol
- `content_provider` (ref_static) → source achat
- `booking_channel` (Booking) → source vente (incohérence de définition non corrigée, §49)
- `booking_service_type` (Booking) → discriminant de service sur `pricing_rule`
- `ref_airline_company`/`ref_cabin_class`/`ref_country` (ref_static), `product_accommodation_room`/`ref_board_type`/`ref_accommodation`/`ref_hotel_chain`/`product_vehicle_model`/`product_vehicle_body_type` (Product/Catalogue, ref_static) → critères par service
- `SolvencyCheckerInterface` (stub Booking) → explicitement écarté du périmètre, reste un sujet Finance dédié
- Document Trigger Rule (module Documents, figé) → FK potentielle non construite (`pricing_payment_modality_detail.rule_id`), à ajouter côté Documents si besoin réel, pas ici

---

## 11. Points restés ouverts

- **Point 25bis** (plafond, `SolvencyCheckerInterface`) — écarté du périmètre, sujet Finance dédié.
- **Éclatement par passager de la commission** — non confirmé spécifiquement, construit par symétrie/prudence.
- **Transfert/Spa/Visa/Bus** — structures improvisées, à reconfronter à Contracting.
- **Maritime** — non couvert, bloquant tant que Product/Catalogue n'a pas de sous-module dédié.
- **`booking_channel.api_in`/`api_out`** — incohérence identifiée, non corrigée.
- **`pricing_rule_log`** — candidat à extraction en système de log générique transverse, non fait.
- **Impact modalité de paiement sur les documents générés** — noté comme FK potentielle côté module Documents, pas construit.
