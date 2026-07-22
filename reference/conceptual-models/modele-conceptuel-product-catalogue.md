# Modèle Conceptuel — Module Product / Catalogue

**Statut** : ✅ FIGÉ — V1.0 complet le 19/07/2026. Couvre les 8 sous-modules du périmètre : **Hôtel, Véhicule, Spa, Visa** (figés 18/07) + **Transfert, Aérien (production billetterie), Bus, Package** (figés 19/07). **Guide** : traité, aucune table nécessaire (voir section dédiée).
**Remplace** : pas de correspondance 1-1 avec le legacy — `ost_sht_hotels*`, `ost_lv_*`, `ost_be_*` consultés comme liste de fonctionnalités à confronter, jamais comme gabarit structurel (principe directeur acté le 16/07 pour tous les modules restants sauf Booking/Contracting/API).
**Convention de nommage** : préfixe `product_`, cohérent avec `party_`/`core_`/`ref_`/`booking_`/`reglement_`/`cash_`/`pointvente_`/`invoicing_`
**Dépend de** : `ref_static` (`ref_accommodation`, `ref_room_category`, `ref_board_type`, `ref_amenity`, `ref_city`, `ref_country`, `ref_option`, `ref_tag`, `ref_accommodation_location_type`, `ref_airline_company`, `ref_cabin_class`), `ref_common` (`ref_language`), `booking_service_type` (Booking, figé — réutilisé par `product_package_component`)
**Documents associés** : `schema-product-catalogue-v1.sql` (complet), `ref-static-accommodation-links.diff` et `ref-static-airline-cabin-extension.diff` (extensions additives de `ref_static`, réouvertures ponctuelles documentées)
**Testé sur** : PostgreSQL 16 réel. Hôtel et Véhicule confrontés à de vrais exports legacy (`ost_sht_hotels*`, `ost_lv_*`) et, pour le Véhicule, à de vraies captures d'écran de production (Hertz Tunisie). Spa confronté à l'export legacy (`ost_be_*`) et à un benchmark concurrent (Thalasseo.com). Visa, Transfert, Aérien, Bus : aucun export legacy, conception neuve à partir de l'expertise métier de l'utilisateur (+ capture legacy du générateur de plan de sièges pour le composant partagé Aérien/Bus). Package : conception neuve, invalide une décision figée le 18/07, confrontée à une capture legacy réelle (fiche "GREECE ISLAND HOPPING").

---

## Principe directeur

Fiche technique **commerciale** de ce qui est vendu — répond à « qu'est-ce que je vends », jamais « qu'est-ce que c'est » (rôle de `ref_static`) ni « combien » (rôle du futur Pricing/Contracting). Indépendant des prix d'achat et de vente.

**Principe transverse acté le 18/07, respecté sur l'ensemble du module** : la base de données porte les invariants structurels (contraintes d'intégrité, plafonds, unicité). La logique métier réelle (règles de calcul, orchestration — y compris la génération d'un plan de sièges depuis un nombre de lignes/colonnes) reste en couche Domain PHP/Symfony (ADR-002), jamais en procédure stockée. **Zéro fonction PL/pgSQL de logique métier dans tout le module**, reconduit du début à la fin.

## Test décisionnel dégagé en session (18/07) — reconduit avec succès sur tout le module

Quand une donnée touche une entité déjà couverte par `ref_static`, la question à se poser pour trancher Catalogue vs `ref_static` : **est-ce que la ligne porte un contenu commercial propre (description, photo, prix futur), ou juste un lien booléen descriptif ?**
- Contenu commercial propre → Product/Catalogue (ex. `product_accommodation_room`, `product_airline_aircraft_type`).
- Lien booléen descriptif, vrai indépendamment de toute décision de vente → `ref_static`, extension additive (ex. liaisons hôtel↔amenities/tags/options, `ref_cabin_class`, `ref_airline_company`).

---

## Sous-module Hôtel

Deux fiches indépendantes, extensions directes de `ref_accommodation` (aucun niveau au-dessus, aucun actif/inactif — rôle du futur Contracting, qui choisira quelles chambres/pensions sont vendables par période).

| Table | Rôle |
|---|---|
| `product_accommodation_room` | Chambre vendable. `room_category_id` → `ref_room_category` (référentiel global réutilisé). `UNIQUE(accommodation_id, room_category_id)` — une catégorie ne peut être affectée qu'une fois par hôtel (multiselect sans doublon, bloqué en base). `surface_sqm` seul, pas de capacité (nb personnes/lits — hors périmètre explicite) |
| `product_accommodation_room_translation` | Description, un seul champ texte par langue |
| `product_accommodation_room_photo` | Galerie 0-N (vs 1 seule photo en legacy) |
| `product_accommodation_room_amenity` | Aménagements propres à la chambre — réutilise `ref_amenity` (pas de vocabulaire dupliqué) |
| `product_accommodation_board` | Pension vendable. `UNIQUE(accommodation_id, board_type_id)`, même logique que la chambre. Volontairement minimal : pas de photo, pas d'horaire (varie par période) |
| `product_accommodation_board_translation` | Description, un seul champ texte par langue |

**Extension additive de `ref_static`** (réouverture ponctuelle documentée, `ref-static-accommodation-links.diff`) :
- `ref_accommodation_amenity` / `_option` / `_location_type_link` / `_tag` : liaisons hôtel↔vocabulaire, pur descriptif — ferme le point ouvert #112 de `ref_static` (partiellement : `ref_hotel_chain` et `ref_supplement` restent non liés, non demandés en session)
- `ref_accommodation_translation` : description courte traduisible de l'hôtel entier
- **Thèmes abandonnés** : `ost_sht_hotels_themes` n'a pas de référentiel `ref_static`, et sa création a été explicitement refusée — cohérent avec le principe déjà acté que le contenu marketing/web n'a pas sa place ici (migre vers un futur CMS, hors périmètre MyGo)

---

## Sous-module Véhicule

100% local — aucun référentiel OctaSoft, contrairement à l'hôtel.

| Table | Rôle |
|---|---|
| `product_vehicle_brand` | Marque, nom propre |
| `product_vehicle_body_type` (+trad) | Carrosserie (Citadine/Compacte/SUV...) — confirmé par capture Hertz |
| `product_vehicle_fuel_type` (+trad) | Énergie — vrai référentiel (décision explicite, pas de texte libre) |
| `product_vehicle_transmission_type` (+trad) | Boîte — vrai référentiel |
| `product_vehicle_equipment_category` / `_equipment` (+trad) | Équipements à 2 niveaux — **réutilisé tel quel par Transfert et Bus** (voir plus bas) |
| `product_vehicle_supplement` (+trad) | Extras (GPS, chaise bébé, conducteur additionnel). **Référentiel séparé** de `ref_supplement` (décision explicite, contrairement à l'amenity de chambre) |
| `product_vehicle_model` (+`_translation`, `_photo`, `_equipment`, `_supplement`) | **Le produit vendu lui-même** — pas de couche "catégorie de location" au-dessus (hypothèse initiale invalidée par la capture Hertz). Exclus : `vitesse`, `tarif_carburant`/`prix_acquisition` (Pricing/Contracting), `stock_voiture` |
| `product_pickup_location` | Lieu de prise en charge/restitution — comptoirs **fixes et prédéfinis** de location. **N'est PAS réutilisable par Transfert** (correction actée 19/07, voir Sous-module Transfert) |
| `product_vehicle_unit` | Véhicule physique précis (plaque unique, `UNIQUE`). Réouverture scopée de Stock Management (16/07). État courant, mutable, pas d'historique en V1 |

**Hors périmètre explicite, confirmé par capture réelle** : livraison à domicile — appartient à Booking/calcul, pas à Product/Catalogue.

---

## Sous-module Spa

| Table | Rôle |
|---|---|
| `product_spa_center_type` (+trad) | Thalasso / Spa Thermal / Spa |
| `product_spa_care_category` (+trad) | Cure / Soin / Programme |
| `product_spa_center` (+`_translation`, `_photo`) | Lieu spa. `accommodation_id` nullable mais structurant quand renseigné (cross-sell hôtel→spa). `city_id` ancrage géographique pour les centres sans hôtel |
| `product_spa_treatment` (+`_translation`, `_photo`) | Le soin vendable |
| `product_spa_treatment_component` | Composition d'un Pack/Cure — liste ordonnée, auto-liaison |
| `product_spa_treatment_center` | Jonction N-N + durée propre à chaque centre |

---

## Sous-module Visa

**Aucun export legacy disponible** — conception neuve à partir d'une capture concurrente (SafarClic.com).

| Table | Rôle |
|---|---|
| `product_visa_entry_type` (+trad) | Mono-entrée / Multi-entrées |
| `product_visa_type` (+trad) | Touristique / Affaires / Transit / Étudiant |
| `product_visa_document` (+trad) | Référentiel réutilisable des justificatifs demandés |
| `product_visa` | `destination_country_id` (FK `ref_country`) + `passport_country_id` nullable (= toutes nationalités) + type d'entrée + type de visa + `is_electronic` + `stay_duration_days` + `processing_delay_days` nullable |
| `product_visa_translation` | Nom + conditions, par langue |
| `product_visa_document_requirement` | Documents requis pour ce visa précis |

**Réutilisé en aval** : `product_package_visa` (sous-module Package, ce même document) relie directement un package à une fiche `product_visa` existante, plutôt que de dupliquer un référentiel de noms de visas en texte libre.

---

## Sous-module Guide — session du 19/07/2026

**Aucune table dans ce module.** Confirmé explicitement par l'utilisateur : contrairement à l'hôtel/véhicule/spa/visa, il n'y a pas de catalogue de guides à choisir sur un site — c'est une simple prestation qui se facture, sans fiche technique commerciale.

**Seule action liée à ce sous-module** : ajouter la ligne `guide` dans `booking_service_type` (Booking, figé) — ajout de donnée, pas réouverture structurelle. Documenté ici mais **pas encore exécuté** (nécessite une session dédiée Booking, cohérent avec la façon dont les autres ajouts `booking_service_type` sont traités).

**Si un besoin de vivier de guides émerge un jour** (langues parlées, spécialités...), il relève de **Party** (un guide est un tiers avec un rôle), jamais de Product/Catalogue.

---

## Sous-module Transfert — session du 19/07/2026

Vente par **catégorie de véhicule** (Berline/Monospace/Minibus...), jamais par modèle/marque nommé comme la location — le client choisit une classe, pas "Peugeot 308".

| Table | Rôle |
|---|---|
| `product_transfer_vehicle_category` (+`_translation`, `_photo`) | `capacity_pax` (hors conducteur, `NOT NULL`), `capacity_luggage` (nullable) |
| `product_transfer_vehicle_category_equipment` | Équipements — **réutilise `product_vehicle_equipment`** (décision explicite : même nature d'objet que pour la location, contrairement à `product_vehicle_supplement` qui, lui, avait divergé de `ref_supplement`) |

**Deux corrections actées en session, par rapport à une proposition initiale plus étoffée** :
1. **Privé/partagé retiré de Catalogue** : relève de l'offre commerciale (futur Contracting), pas une propriété structurelle de la catégorie de véhicule.
2. **`product_pickup_location` NE s'applique PAS au Transfert** — corrige une hypothèse notée à tort dans `sujets-reportes.md` ("Transfert réutilise déjà `product_pickup_location`"). Cette table sert des comptoirs fixes et prédéfinis (agence aéroport, agence centre-ville) pour la location de voiture ; un client de transfert donne une adresse/lat-long **libre**, jamais un choix dans une liste. Le point A/B du transfert est donc un détail de réservation (Booking), pas un produit catalogue — le sous-module Transfert se réduit ainsi à une seule vraie table.

---

## Sous-module Aérien (production ponctuelle de billetterie) — session du 19/07/2026

Périmètre volontairement réduit, confirmé : le cas normal est le GDS live sans fiche produit. Ce sous-module ne couvre que la production ponctuelle (compagnies hors GDS/OctaSoft).

| Table | Rôle |
|---|---|
| `ref_airline_company` (+trad) | Compagnie aérienne — **`ref_static`**, extension additive (`ref-static-airline-cabin-extension.diff`). Descriptif, ajout local **permis** (`oct_code` nullable, contrairement à `ref_cabin_class`) — cohérent avec le périmètre réduit (compagnies que le référentiel central ne couvre pas forcément) |
| `ref_cabin_class` (+trad) | Classe cabine (Economy/Business...) — **`ref_static`**, référentiel fermé et universel, `oct_code NOT NULL` ("ça doit être dans les static data OctaSoft", demande explicite de l'utilisateur) |
| `product_airline_aircraft_type` (+trad) | Type d'appareil de la flotte d'une compagnie — contenu commercial (ce qui sert à vendre un billet), `total_capacity` |

Le plan de sièges est porté par le composant partagé `product_seat_map`/`product_seat` (voir section dédiée) — pas de table spécifique à l'Aérien.

---

## Sous-module Bus — session du 19/07/2026

Un seul référentiel de flotte, utilisé aussi bien pour le trajet de ligne (`booking_service_type = 'bus'`, siège numéroté) que pour le ramassage groupé — l'objet vendu (le bus) est le même, seul le contexte de réservation change (décision explicite, pas de duplication de table).

| Table | Rôle |
|---|---|
| `product_bus_model` (+`_translation`, `_photo`) | Nom propre (ex: "Mercedes Tourismo 50 places"), `capacity` |
| `product_bus_model_equipment` | Équipements — **réutilise `product_vehicle_equipment`**, même logique que Transfert |

Points de ramassage groupé : gérés en Booking au moment de la réservation (pas de fiche catalogue dédiée, même raisonnement que le point A/B du Transfert).

---

## Plan de sièges — composant PARTAGÉ Aérien/Bus — session du 19/07/2026

Reconstruit et amélioré depuis le générateur legacy (capture utilisateur : "Nbr colonnes gauche" / "Nbr colonnes droite" / "Nbr lignes" → grille de sièges, avec possibilité d'exclure un emplacement comme celui du conducteur).

| Table | Rôle |
|---|---|
| `product_seat_map` | `aircraft_type_id` **OU** `bus_model_id` — exactement un des deux (`CHECK` d'exclusivité + unicité partielle sur chacun : un seul plan par appareil/bus). Porte les paramètres du générateur (`columns_left`, `columns_right`, `rows_count`) |
| `product_seat` | Siège individuel matérialisé (`row_number`, `column_code`, `seat_label` type "1A"), `cabin_class_id` nullable (→ `ref_cabin_class`, pertinent Aérien, NULL en pratique pour Bus), `is_available` (false = exclu, ex. emplacement conducteur — reconduit de la capture legacy) |

**Décisions clés** :
- Une seule paire de tables partagée entre Aérien et Bus (pas de duplication) — le `CHECK` d'exclusivité évite une table pivot polymorphe générique, cohérent avec le rejet EAV déjà acté partout dans le projet.
- La génération grille→sièges (algorithme qui transforme 3 nombres en N lignes `product_seat`) est de la **logique Domain PHP** (ADR-002), jamais une fonction stockée. Une fois générée, chaque siège reste éditable individuellement.
- **Portée V1 strictement limitée au plan-TEMPLATE.** L'attribution d'un siège précis à un passager pour une réservation précise est un **trou identifié côté Booking**, de même nature que le trou Guide — à traiter dans une future session Booking dédiée (`booking_flight_detail`/`booking_bus_detail` avec `seat_id`), **pas construit dans cette session**.

---

## Sous-module Package — session du 19/07/2026

**Réouverture documentée** de la décision figée le 18/07 ("packages/circuits = regroupement `booking_folder`, pas de fiche produit avec prix propre"). Invalidée par une capture legacy réelle ("GREECE ISLAND HOPPING", prix propre 5490, composition Vol+Transfert+Hébergement+Excursions) et par la description de l'utilisateur d'un vrai besoin de composition commerciale (Hôtel + transfert optionnel + circuits optionnels + soirée optionnelle).

Volontairement léger : le gros du travail (tarification, disponibilité) est annoncé par l'utilisateur comme relevant du futur Contracting.

| Table | Rôle |
|---|---|
| `product_package` (+`_translation`, `_photo`) | `duration_days`/`duration_nights` nullable. **Pas de prix stocké** — cohérent avec le principe tenu sur tout le reste du module, malgré la capture legacy qui en affichait un |
| `product_package_country` | Pays multi-select (N-N `ref_country`) — remplace le couple Pays/Destination(zone) de la capture legacy. **"Destination" (zone large type Europe/Asie) explicitement abandonné**, pas remplacé par un référentiel équivalent (décision utilisateur 19/07) |
| `product_package_tag` | Thèmes — **réutilise `ref_tag`** (même référentiel que celui déjà utilisé pour l'hôtel via `ref_accommodation_tag`), pas de vocabulaire dupliqué |
| `product_package_component` (+`_translation`) | Ligne de composition : `service_type_code` (**réutilise `booking_service_type`**) + `is_optional` + `display_order`. Table dédiée avec `id` propre (pas une simple jonction `package_id`+`service_type_code`) car un même type de service peut apparaître plusieurs fois dans un package (ex: "Circuit Hammamet" ET "Soirée cabaret" sont deux lignes optionnelles distinctes, toutes deux `service_type_code='excursion'`) — chaque ligne porte donc son propre libellé traduit |
| `product_package_visa` | Visa(s) requis — **réutilise directement `product_visa`** (fiches déjà structurées : pays, type d'entrée, documents requis), remplace la checklist en texte libre du legacy ("Visa Thailand", "Visa Dubai"...). Relation commerciale structurante (l'agent peut vendre le visa associé), pas un simple contenu CMS |

**Champ explicitement abandonné, hors périmètre confirmé** : gestion des tranches d'âge enfant (min/max age × 2 tranches, vue dans la capture legacy) — relève du futur Pricing/Contracting (tarification différenciée adulte/enfant), même logique que tous les autres champs prix déjà exclus du module.

---

## Décisions clés et revirements assumés

1. **Périmètre du module confirmé par l'utilisateur** (18/07) : hébergement, packages/circuits, véhicules, vol/billetterie (production ponctuelle), transfert, visa, guide, bus groupé, maritime (à prévoir "au cas où"). Complété le 19/07 par la découverte de deux besoins non annoncés initialement, absorbés dans le sous-module Package : Circuits (city tour, balade en mer, soirées) et Activités (billets parc aquatique, accès piscine) — finalement rattachés à `product_package_component` plutôt qu'à une famille de produits séparée, une fois le vrai besoin de composition/package clarifié.

2. **`room_category_id` (chambre hôtel) : aller-retour dans la session** (18/07). Documenté pour éviter toute confusion en relecture future.

3. **Anomalies legacy détectées, jamais confirmées en production, hypothèse retenue documentée** (18/07) : doublon jonction simple/table riche chambre-pension ; plaque dupliquée dans l'export véhicule.

4. **`product_vehicle_rental_category` proposée puis retirée** (18/07) : "308 ou similaire" est une convention d'affichage, pas une entité de vente distincte.

5. **Réouverture scopée de "Stock Management hors périmètre"** (18/07) : `product_vehicle_unit`.

6. **Description : un seul champ partout, sauf exception justifiée** (18/07, reconduit 19/07 sur Transfert/Aérien/Bus/Package).

7. **Centre spa découplé de l'hôtel, puis reconnecté avec un rôle différent** (18/07).

8. **Durée du soin spa déplacée du soin vers la jonction soin↔centre** (18/07).

9. **`product_visa.passport_country_id` nullable** (18/07).

10. **`product_pickup_location` invalidé pour le Transfert** (19/07) — hypothèse notée dans `sujets-reportes.md` corrigée en session : ce référentiel sert des comptoirs fixes (location de voiture), pas des adresses libres saisies par le client (transfert). Le sous-module Transfert en ressort volontairement plus léger qu'anticipé (une seule table).

11. **Privé/partagé retiré du sous-module Transfert** (19/07) : reconnu par l'utilisateur lui-même comme relevant du futur Contracting, pas une propriété structurelle du produit catalogue.

12. **Classe cabine déplacée vers `ref_static`, pas Catalogue** (19/07) : demande explicite de l'utilisateur ("ça doit être dans les static data OctaSoft"), cohérent avec le régime `oct_code NOT NULL` déjà appliqué à `ref_board_type`. Contraste documenté avec `ref_airline_company`, elle, à ajout local permis.

13. **Plan de sièges : conception "B complet" retenue** (19/07) sur la base d'une capture legacy réelle du générateur (colonnes gauche/droite + nb lignes), plutôt qu'une simple liste plate de sièges — la profondeur demandée (rendu visuel façon sélection de siège) justifiait la complexité supplémentaire. Table partagée Aérien/Bus avec `CHECK` d'exclusivité plutôt que deux tables dupliquées.

14. **Package : réouverture documentée d'une décision figée** (19/07) — voir section dédiée ci-dessus. Premier cas du module où une décision "figée" a été explicitement révisée suite à une preuve terrain contradictoire, cohérent avec le principe du projet ("figé" ne veut jamais dire "définitif à 100%").

15. **Tranches d'âge enfant du package abandonnées** (19/07) : relève de Pricing/Contracting, cohérent avec l'exclusion systématique de tout ce qui touche au prix dans ce module.

---

## Points restés ouverts, reportés en `sujets-reportes.md`

- **Trou Booking — attribution de siège** : le plan de sièges défini ici est un template ; l'attribution d'un siège précis à un passager pour une réservation précise nécessite une extension `booking_flight_detail`/`booking_bus_detail` (avec `seat_id`) — à traiter en session Booking dédiée, comme le trou Guide.
- **Trou Booking — ligne `guide`** : toujours pas ajoutée à `booking_service_type` (documenté depuis le 18/07, action à faire en session Booking).
- `spa`/`accès piscine`/`train` (présents dans `booking_service_type` mais statut de couverture Catalogue jamais explicitement confirmé) — non retraité cette session, toujours en attente.
- Livraison à domicile (location de voiture) — confirmé hors périmètre Product/Catalogue, relève de Booking/calcul.
- `ref_static` : point #112 partiellement fermé — `ref_hotel_chain` et `ref_supplement`, liaisons vers `ref_accommodation` non construites.
- `product_vehicle_unit` : pas d'historique en V1.
- `product_spa_treatment_component` : pas de protection anti-cycle profond.
- Anomalies legacy non vérifiées en base de production réelle.
- **Maritime** : toujours non traité (mentionné comme "à prévoir au cas où" le 18/07, jamais repris en session dédiée) — aucun sous-module construit à ce jour.
