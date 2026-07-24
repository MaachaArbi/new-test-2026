# Modèle conceptuel — Référentiel Hébergement & Géographie (ref_)

**Statut** : V1.0 — figé le 17 juillet 2026
**Dépend de** : Référentiel commun étendu (`ref_language`, `ref_currency` — voir `ref-common-hebergement-extension.diff`)
**Documents associés** : `schema-ref-static-v1.sql`, `schema-ref-common.sql` (mis à jour), `ref-common-hebergement-extension.diff`
**Testé sur** : PostgreSQL 16 réel, scénarios synthétiques construits en session (aucun export brut OctaSoft Static Data réel disponible à ce jour).

---

## Principe directeur

Ce module conçoit **uniquement le côté client** : les tables miroir locales que ce client remplit en tirant (pull) depuis l'API OctaSoft Static Data, plus la possibilité d'ajouts locaux propres au client selon l'entité. OctaSoft Static Data lui-même (moteur de rapprochement fournisseur, API d'exposition) est un **produit séparé**, avec son propre chat/Project dédié — jamais conçu ici.

Conception neuve (cohérent avec le principe directeur acté le 16/07 pour tous les modules restants) : le legacy (`ost_sht_pays`, `ost_sht_hotels`, `ost_sht_chambre`, `ost_sht_arrangement`...) a servi de liste de fonctionnalités à confronter, jamais de gabarit structurel — exemple concret écarté : `ost_sht_pays` contenait "Turquie"/code `TURK` dupliqué 7 fois, symptomatique du mapping manuel `id_rapprochement` que `oct_code` rend obsolète.

---

## Convention transverse à toutes les entités

| Aspect | Règle |
|---|---|
| Identifiants | `id BIGINT identity` + `public_id UUID` (ADR-018), comme partout |
| `oct_code` | Code de réconciliation universel OctaSoft. **NOT NULL** = ajout local interdit (référentiel fermé). **NULLABLE + UNIQUE partiel** = ajout local permis (une ligne locale a `oct_code = NULL` en permanence, jamais saisi manuellement) |
| Signal "ajout local" | `oct_code IS NULL`, pas de colonne booléenne dédiée (jugée redondante) |
| Synchronisation | PULL uniquement, jamais de webhook/push côté client |
| Conflit `oct_code` | Aucune structure de rapprochement en V1 — un ajout local ne pouvant jamais recevoir de vrai `oct_code`, un doublon (import tardif de la même entité réelle) reste un doublon visuel, résolu manuellement si besoin |
| Ordre d'import | Une ligne dont la FK ne résout pas un `oct_code` parent déjà présent localement est **rejetée** (jamais acceptée avec FK NULL temporaire), à retraiter au batch suivant |
| Mapping fournisseur | **Jamais modélisé côté client.** Le rapprochement vocabulaire-fournisseur (ex: Webbeds "Half Board" → `ref_board_type` "Demi-pension") est fait en amont par OctaSoft Static Data. Les données futures (Provider Integration) arriveront déjà taguées avec le bon `oct_code` — jointure directe, jamais de table de mapping intermédiaire |
| EAV / table générique | Rejeté par principe (cohérent avec Booking) — chaque domaine a sa propre table, convention de colonnes reconduite, jamais de table pivot partagée |

---

## Entités

| Table | `oct_code` | Rôle |
|---|---|---|
| `ref_country` | NOT NULL | Pays (ISO alpha2/alpha3/numeric/dial) |
| `ref_region` | NOT NULL | Zone touristique, rattachée au pays (ex: Hammamet) |
| `ref_city` | NOT NULL | Ville, rattachée obligatoirement à une région (hiérarchie stricte) |
| `content_provider` | NOT NULL | Fournisseur de contenu (Hotelbeds/Webbeds/GNV...) — **entité fondatrice** pour Provider Integration |
| `content_provider_language` | — | Langues dans lesquelles un fournisseur accepte d'être interrogé (jonction simple) |
| `ref_board_type` | NOT NULL | Type de pension/arrangement (LS/LPD/DP/PC/All Inclusive) |
| `ref_accommodation_rental_mode` | — (fixe, interne) | `room` / `whole_unit` — distinction structurelle propre à ce projet, pas OctaSoft |
| `ref_property_category` | NOT NULL | **TYPE** d'hébergement (Hôtel/Villa/Appartement/Maison d'hôte) — porte `rental_mode_code` |
| `ref_property_rating` | NOT NULL | **CLASSEMENT** en étoiles — indépendant de la catégorie |
| `ref_accommodation` | NULLABLE | Hébergement lui-même — première entité avec ajout local |
| `ref_amenity_type` / `ref_amenity` | NULLABLE | Équipement physique avec icône (Animaux autorisés, Salon de coiffure...) |
| `ref_hotel_chain` | NULLABLE | Chaîne hôtelière (nom propre, pas de traduction) |
| `ref_tag_category` / `ref_tag` | NULLABLE | Tags de vente |
| `ref_accommodation_location_type` | NULLABLE | Implantation (Centre-ville/Bord de mer/Palmeraie) |
| `ref_room_category` | NULLABLE | Catégorie de chambre — référentiel **global partagé** |
| `ref_option` | NULLABLE | Souhait gratuit sans engagement (Arrivée tardive si possible, VIP...) |
| `ref_charge_unit` / `ref_charge_frequency` | — (fixes, internes) | Dimensions du mode de facturation d'un supplément |
| `ref_supplement` | NULLABLE | Supplément/réduction — vocabulaire + plage récurrente + mode de facturation, **pas le montant** |

Toutes les entités catégorielles ont leur `_translation` (nom traduit), sauf `ref_hotel_chain` et `ref_accommodation` (noms propres, jamais traduits).

---

## Décisions structurantes

### 1. Trois familles d'entités selon la synchronisation OctaSoft
Découvertes en cours de session, pas anticipées au cadrage initial :
- **Miroir fermé** (`oct_code NOT NULL`) : Pays, Région, Ville, Langue, Devise, Board Type, Property Category, Property Rating.
- **Miroir + ajout local** (`oct_code NULLABLE`) : Hébergement et tout son vocabulaire (amenities, tags, options, chaînes, suppléments, catégories de chambre, type d'implantation).
- **Purement local, jamais synchronisé** (hors périmètre construit cette session — voir Points ouverts) : entités métier internes sans lien OctaSoft (ex: statut de traitement de réservation), et entités "pas encore couvertes par OctaSoft" (aéroports, compagnies aériennes).

### 2. Hiérarchie géographique stricte Pays → Région → Ville
Confirmée le 17/07 : aucune ville orpheline directement rattachée au pays. Le pays/la région ne sont **jamais dupliqués** en aval (pas de `country_id` sur `ref_city`) — atteignables uniquement par transitivité via la FK, pour éviter toute dérive entre deux copies du même `oct_code`.

### 3. `content_provider` — entité fondatrice pour Provider Integration
Découverte structurante : aucune notion de "fournisseur technique" n'existait nulle part dans le schéma avant cette session (`booking_provider_snapshot` n'a même pas de FK provider). Ce module crée donc la table que le futur module Provider Integration (module 4) devra **réutiliser par extension** (table compagnon, même pattern que `cash_payment_method_routing` sur `settlement_payment_method`), jamais recréer ni dupliquer. Portée volontairement minimale (`id`, `oct_code`, `name`) — le futur module ajoutera endpoints/credentials/format de flux sans rouvrir cette table.
Distincte de `booking_payment.provider_reference` (passerelle de paiement) — deux catégories différentes, cycle de vie différent, tranché explicitement le 17/07.

### 4. `ref_property_category` — revirement explicite sur la typologie d'hébergement
Le cadrage initial (17/07, avant le début de la session de construction) actait *"la typologie d'hébergement est une simple colonne `type`, pas de référentiel à part"*. En cours de session, une fois le pattern `oct_code`+`_translation` établi sur toutes les autres entités, ce choix a été révisé : `ref_property_category` est devenue un référentiel à part entière — une colonne simple n'aurait pas permis la synchronisation ni la traduction proprement. Documenté ici comme revirement assumé, pas un oubli.

### 5. Distinction TYPE (catégorie) vs CLASSEMENT (rating) — deux référentiels indépendants
Confirmé le 17/07 après question explicite : `ref_property_category` (Hôtel/Villa/Appartement/Maison d'hôte) et `ref_property_rating` (1 à 5 étoiles) sont deux référentiels **sans lien structurel**, chacun une FK séparée sur `ref_accommodation`. `stars_number` sur `ref_property_rating` est un simple raccourci d'affichage numérique (évite de parser le nom traduit), nullable pour les classements non numériques.

### 6. `rental_mode` porté par la catégorie, pas par l'hébergement
Distinction "location par chambre" (`room`) vs "location à l'unité globale" (`whole_unit`), demandée dès le cadrage initial pour éviter le contournement legacy ("S+1"/"S+2" comme chambre unique). D'abord envisagée comme attribut de `ref_accommodation`, **déplacée sur `ref_property_category`** en cours de session (17/07) : chaque catégorie détermine son mode de location de façon déterministe (Hôtel→room, Villa/Appartement/Maison d'hôte→whole_unit) — évite un état incohérent (une Villa marquée `room` par erreur). `ref_accommodation_rental_mode` reste un référentiel fixe **interne à ce projet**, jamais fourni par OctaSoft.
Capacité descriptive (nombre de chambres pour le mode `whole_unit`) : reportée, voir Points ouverts.

### 7. Pas de mapping fournisseur côté client — le seul principe transverse du module
Point 6 du cadrage initial anticipait une table de mapping `board_type ↔ fournisseur`. Clarifié en session : ce travail est fait par le moteur de rapprochement OctaSoft Static Data en amont (produit séparé, hors périmètre) — quand le client télécharge plus tard les données spécifiques à un fournisseur (futur Provider Integration), elles arrivent **déjà taguées** avec le bon `oct_code`. Aucune table de mapping à construire ou maintenir côté client, ni pour Board Type ni pour aucune autre entité future du même type. Distinction gardée claire avec les vraies tables de jonction (ex: `content_provider_language`), qui restent nécessaires quand la relation elle-même est réelle.

### 8. `oct_code` toujours `VARCHAR`, jamais numérique
Choix initial `VARCHAR(50)` retenu malgré des valeurs réelles communiquées sous forme entière pour les langues (`1`/`2`/`3`) — un code d'identification reste une chaîne par convention (pas d'opération arithmétique dessus, absorbe sans souci les futurs codes alphanumériques d'autres entités).

### 9. `ref_supplement.from_mmdd`/`to_mmdd` — plage récurrente, format compact pour la performance
Plage annuelle récurrente (ex: supplément fêtes de fin d'année du 20/12 au 05/01, applicable toutes les années). Stockée en `SMALLINT` au format `MMDD` (mois×100+jour) plutôt qu'en `DATE` (porterait une année arbitraire et fausse) ou en deux colonnes mois/jour séparées — choisi explicitement pour la performance en lecture (comparaison entière directe, indexable, aucun calcul à la volée). Logique de lecture applicative : `from <= to` → `BETWEEN` ; `from > to` (plage à cheval sur le nouvel an) → `>= from OR <= to`. Testée sur PostgreSQL réel (cas Noël 20/12→05/01, cas permanent sans plage).

### 10. Extension additive de `ref_language`/`ref_currency` — pas de doublon
L'utilisateur a explicitement demandé une seule entité langue/devise pour tout le projet plutôt qu'un doublon avec le référentiel OctaSoft Static Data. `ref_language`/`ref_currency` (Référentiel commun V1, figé) ont été étendus de façon strictement additive — `code` (PK `VARCHAR`) inchangé, aucune table existante référençant ces colonnes n'a été modifiée. Exception documentée à ADR-018 (pas de passage en `BIGINT identity`, ces tables restent petites). Voir `ref-common-hebergement-extension.diff`.
`is_rtl` réutilisé comme "direction" du texte — pas de colonne dupliquée.
Seed `ref_currency.oct_code` : **PLACEHOLDER** (`'PLACEHOLDER-TND'` etc.), aucune vraie valeur communiquée — à remplacer au premier import réel avant production. Seed `ref_language.oct_code` : vraies valeurs OctaSoft (`'1'`/`'2'`/`'3'`).

---

## Limites V1 assumées

- **Aucun export brut OctaSoft Static Data réel disponible** à ce jour — toutes les hypothèses de structure sont construites par déduction et testées avec des données synthétiques, jamais confrontées à un vrai payload d'API.
- **`ref_currency.oct_code`** : placeholders, pas de vraies valeurs. Premier import réel obligatoire avant production.
- **`ref_accommodation`** : volontairement incomplet — pas de description, photos, capacité descriptive, ni liaison vers `ref_amenity`/`ref_tag`/`ref_option`/`ref_hotel_chain`/`ref_supplement`. Voir Points ouverts.
- **Entités "pas encore dans OctaSoft"** (aéroports, compagnies aériennes/maritimes) : non construites cette session, le référentiel central ne les couvre pas encore.
- **Comportement de conflit `oct_code`** structurellement impossible à déclencher (l'utilisateur ne peut jamais saisir `oct_code` manuellement), donc jamais testé en conditions réelles — juste documenté comme non bloquant.

## Points ouverts

Voir `sujets-reportes.md` #32-#35 pour le détail complet :
- Contenu riche hébergement (description multilingue, photos, capacité descriptive, toutes les liaisons `ref_accommodation` ↔ vocabulaire) — extensions additives 1-N/1-1 sur `ref_accommodation.id`, jamais de réouverture de la table de base.
- Entités pas encore couvertes par OctaSoft (aéroports, compagnies aériennes/maritimes) — à modéliser dès qu'OctaSoft les expose.
- Organisation transverse de tous les référentiels du projet (regroupement thématique vs. un référentiel par module métier) — question de fond dépassant ce module, à trancher dans une session dédiée transverse.
- Renommage `booking_hotel_detail` → `booking_accommodation_detail` + branchement FK réelle vers `ref_accommodation` — action actée mais explicitement reportée à une session dédiée au chat pilote Booking.
