# Modèle Conceptuel — Module Booking (réservations multi-services)

**Statut** : Figé (V1.1) — 16 juillet 2026, validé sur >30 réservations réelles (hôtel + maritime)
**Remplace** : `ost_sht_reservation*` (hôtel), `ost_billetterie_reservation*` (vol/train/bus), `ost_maritime*` (maritime), et tout futur système par-service de l'ancien logiciel
**Convention de nommage** : préfixe `booking_`, cohérent avec `party_`/`core_`/`ref_`
**Dépend de** : `party_` (tiers, bureaux), `ref_` (langues, devises)
**Hors périmètre explicite** : règlements client/fournisseur, lettrage, échéances — traités dans une conversation dédiée (voir `sujets-reportes.md`)

## Pourquoi ce découpage

Le legacy duplique un même en-tête de réservation (client, statut, timbre, remise, marges, dates XML...) dans chaque module de service (`ost_sht_reservation`, `ost_billetterie_reservation`, `ost_maritime`...), confirmé colonne par colonne sur plusieurs tables comparées. Booking corrige ça avec un **pivot commun** (`booking`) qui porte tout ce qui est réellement partagé, et des **extensions optionnelles par service** pour le reste.

**Décision explicitement réaffirmée (16/07)** : une alternative "table générique `reservation_item` + hiérarchie dynamique (EAV)" a été envisagée puis écartée après confrontation aux mêmes données réelles — elle aurait réabsorbé les deux problèmes que ce modèle élimine (colonnes/valeurs orphelines selon le type, calcul de total par traversée d'arbre au lieu d'un `SUM()` plat), et aurait supprimé la friction structurelle qui a permis de découvrir la moitié des règles métier de ce module (annulation par chambre, raisons multiples simultanées, `marge_b2b` ≠ `vente-achat`...). Un schéma typé qui refuse ce qui ne rentre pas est ce qui a forcé les bonnes questions ; un modèle générique n'aurait jamais buté sur rien.

## Principe directeur

**1 `booking` = 1 service = 1 fournisseur**, jamais un service composé. Un "voyage organisé" est un regroupement de plusieurs `booking` dans un même `booking_folder`. On découpe :
- **Le dossier** (qui regroupe, hérite le client) → `booking_folder`
- **La réservation** (pivot, partitionné par date) → `booking`
- **Le spécifique par service** → extensions 1-1 (hôtel, location voiture) ou 1-N (chambres, segments de transport)
- **La composition du prix** → `booking_charge` (additif, compose le total)
- **La répartition de la marge entre bénéficiaires** → `booking_settlement` (redécoupe ce qui est déjà compté, jamais additif)
- **Le prix de revente d'un distributeur à SON client** → `booking_settlement.resale_price_amount` (purement informatif, hors périmètre financier de MyGo)
- **Qui paie** → `booking_payer_split`
- **Le voyageur/conducteur** → `booking_traveler`

## Entités — Module `booking_` (28 tables logiques)

### Référentiels (table, jamais ENUM — extensible sans migration)
| Table | Rôle |
|---|---|
| `booking_service_type` (+translation) | Services ayant un fournisseur/cycle de vie propre : hôtel, vol, transfert, bus, excursion, location voiture, spa, accès piscine, visa, maritime, train |
| `booking_status` (+translation) | Statuts internes (draft/on_option/confirmed/completed/cancelled/no_show) |
| `booking_channel` | Canal de création (backoffice/web/api_in/api_out) |
| `booking_on_request_reason` (+translation) | Raisons de mise sur demande, avec `approval_type` (supplier/internal) |
| `booking_charge_type` (+translation) | Types de ligne de prix (tarif, remise, frais, timbre, taxe séjour, commission, retenue à la source, transport véhicule, hébergement à bord, location, suppléments, assurance passager/véhicule, repas, remboursement...) |

### Pivot et dossier
| Table | Rôle |
|---|---|
| `booking_folder` | Dossier. Client "principal" hérité, bureau. Regroupement de N `booking` |
| `booking` | **Pivot**, partitionné par `booking_date` (date de création, PAS la date de séjour). Voir détail colonnes ci-dessous |

### Extensions par service
| Table | Rôle |
|---|---|
| `booking_hotel_detail` | Extension 1-1 hôtel : code/nom hôtel figé, pension (1 seule par réservation) |
| `booking_hotel_room` | 1-N chambres par réservation hôtel |
| `booking_transport_segment` | 1-N tronçons (aller/retour/correspondance/multi-destination), générique vol/train/maritime/transfert |
| `booking_car_rental_detail` | Extension 1-1 location voiture : véhicule, lieux et horaires précis (TIMESTAMPTZ) |

### Voyageur
| Table | Rôle |
|---|---|
| `booking_traveler` | Voyageur/conducteur, snapshot figé. `hotel_room_id` optionnel, champs billet (num, PNR, classe), document typé + date/lieu de naissance, permis de conduire, `is_pax_leader` |

### Argent (constate des faits, ne génère jamais d'échéance)
| Table | Rôle |
|---|---|
| `booking_charge` | Décomposition **additive agrégée** du prix (`SUM(vente_amount) = booking.total_vente_amount`), rattachable à un voyageur et/ou un segment. `metadata` JSONB pour le détail verbeux propre à une ligne |
| `booking_settlement` | Répartition de la marge déjà comptée entre bénéficiaires (jamais additif) + `resale_price_amount` (prix de revente informatif d'un distributeur) + `rate` (taux d'origine, optionnel) |
| `booking_payer_split` | Répartition du montant à payer entre plusieurs `party_account`, historisée |
| `booking_payment` | Paiement effectif reçu — **conception provisoire, à revoir** (voir `sujets-reportes.md`) |

### Annulation
| Table | Rôle |
|---|---|
| `booking_cancellation_policy` | Barème d'annulation, rattaché **par chambre** en général pour l'hôtel (`room_id`), pas à la réservation entière |
| `booking_cancellation_tier` | Paliers (jours avant, heure précise, min/max séjour, type/valeur de pénalité) |

### Workflow et traçabilité
| Table | Rôle |
|---|---|
| `booking_on_request_flag` | Raison(s) de mise sur demande, 1-N (une réservation peut cumuler plusieurs raisons simultanées) |
| `booking_approval` | Événements de confirmation/rejet/approbation (fournisseur, interne, demande d'annulation) |
| `booking_note` | Notes typées par audience (interne/client/conditions de vente) |
| `booking_revision` | Snapshot avant modification post-confirmation, pour notifier le fournisseur |
| `booking_log` | Log unifié des événements, avec `status_code_snapshot` (historique de statut sans table dédiée) |

### Technique
| Table | Rôle |
|---|---|
| `booking_provider_snapshot` | Payload API brut fournisseur, isolé du chemin de lecture chaud |

## `booking` — colonnes clés (pivot)

Dates : `booking_date` (partition, création), `start_date`/`end_date` (séjour/service, DATE — précision horaire déléguée aux extensions type `booking_car_rental_detail`).
Statut : `status_code`, `is_on_request` (booléen **indépendant** du statut — confirmé : peut coexister avec `confirmed`), `is_locked`, `is_disputed`.
Tiers : `customer_account_id` (dénormalisé), `supplier_account_id` (**nullable**, confirmé : "Sans Fournisseur" est un cas réel), `office_account_id`, `assigned_agent_account_id`/`assigned_at`.
Contact : `contact_name`/`contact_phone`/`contact_email`/`contact_address` — snapshot du réservant (B2C = client, B2B = agence + personne ayant réservé), scopé par réservation.
Argent : devises/taux achat et vente séparés, totaux dénormalisés, `cancellation_fee_achat_amount`/`vente_amount`.
Autres : `channel_code`, `option_expiry_at` (BOOK_NOW_PAY_LATER, fixe), `trip_type` (aller simple/aller-retour/multi-destination, stocké tel quel, affichage uniquement), `origin_booking_id` (auto-référence, lien prévente→confirmée), `supplier_booking_reference`, `voucher_url`, `exclude_from_invoicing`, `intended_payment_method`, `price_breakdown` (JSONB, documentaire).

## Décisions clés et justification

1. **Partitionnement par `booking_date`**, PK composite `(id, booking_date)`. **Automatisation pg_partman non incluse** — PostgreSQL ne crée jamais de partition tout seul ; sans pg_partman, les nouvelles résas tombent silencieusement dans `booking_default` (fonctionne mais perd l'intérêt du partitionnement). À mettre en place avant production.
2. **FK applicatives, pas SQL**, entre `booking` et ses tables filles (contrainte PostgreSQL sur table partitionnée).
3. **`is_on_request` indépendant de `status_code`** — confirmé sur données réelles (`etat`/`surDemande`/`confirmationHotel` cohabitent en legacy).
4. **`booking_on_request_flag` en 1-N**, pas une colonne unique — confirmé : une réservation peut cumuler plusieurs raisons simultanées (stock ET solde, par exemple).
5. **`booking_charge` vs `booking_settlement` : deux logiques distinctes.** `booking_charge` compose le total par addition. `booking_settlement` redécoupe la marge déjà comptée — jamais une somme en plus. Confirmé indépendamment sur hôtel, vol et maritime.
6. **`booking_settlement.resale_price_amount`** — un distributeur peut revendre à son propre client à un prix supérieur à ce qu'il paie à l'agence (confirmé : `marge_b2b` réel ≠ `vente-achat`). Cette 3ᵉ transaction est hors périmètre financier de la réservation — purement informative, jamais sommée.
7. **Annulation rattachée par chambre** (hôtel) ou par segment (maritime, via `booking_charge.segment_id` pour les remboursements) — pas par réservation entière. Confirmé sur cas réels des deux services indépendamment.
8. **Pas de détail jour/chambre/personne en relationnel** — abandonné pour la performance et l'absence de besoin de requêtage transverse ; vit dans `price_breakdown`/`booking_charge.metadata` (JSONB).
9. **"Amicale" n'existe pas comme concept dédié** — un `party_account` comme un autre, apparaît dans `booking_payer_split`.
10. **Booking constate des faits, ne génère pas d'échéances** — `booking_settlement`/`booking_payment` immuables et datés ; calcul réel de solde/échéance délégué à un futur module Règlements, via interface (`SolvencyCheckerInterface`, stub).
11. **BOOK_NOW_PAY_LATER géré dans Booking** (`option_expiry_at`, fixe) — annulation automatique via job planifié, jamais un trigger DB.
12. **`origin_booking_id`** — une réservation provisoire ("prévente") peut être remplacée par une nouvelle ligne "confirmée" plutôt que mise à jour en place (confirmé sur données maritime réelles).
13. **`trip_type` stocké, pas dérivé** — la dérivation depuis le nombre de segments serait ambiguë (retour par un port différent = toujours "aller-retour" ou déjà "multi-destination" ?). Besoin d'affichage uniquement, confirmé.

## Hors périmètre (reporté, cf. `sujets-reportes.md`)

**Règlements client/fournisseur** (paiements en plusieurs pièces, lettrage, soldes, échéances) — explicitement mis de côté pour une conversation dédiée, "plus compliqué que ce qui a été vu", conception à revoir entièrement.
Excursion/spa/visa/pool_access/bus/train, transfert : couverts par la structure générique, à affiner au premier cas concret. Système de notification générique cross-module : à extraire seulement si un 2ᵉ module en exprime le besoin.
