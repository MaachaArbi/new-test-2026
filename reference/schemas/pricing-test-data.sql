-- ============================================================
-- pricing-test-data.sql — régénéré le 19/07/2026 pour refléter les
-- corrections apportées en session : party_account_group (Party, ex-
-- pricing_affiliate_group), ref_country_group (ref_static, ex-
-- pricing_country_group), pricing_margin_detail/pricing_commission_detail
-- à 3 colonnes (value/value_child/value_infant, ex-éclatement en lignes
-- par passenger_type_code).
--
-- Ordre d'exécution requis (base vierge) :
--   schema-ref-common.sql
--   schema-party-account-v1.sql
--   party-account-group-extension.diff (extrait en .sql, cf. note de fin)
--   schema-core-identity-v1.sql
--   schema-booking-v1.sql
--   schema-ref-static-v1.sql
--   ref-static-country-group-extension.diff (extrait en .sql)
--   schema-product-catalogue-v1.sql
--   schema-pricing-v1.sql
--   pricing-test-data.sql (ce fichier)
-- ============================================================

-- ------------------------------------------------------------
-- Géographie + vocabulaire hébergement (scénario hôtel)
-- ------------------------------------------------------------
INSERT INTO ref_country (oct_code, alpha2, alpha3, numeric_code, dial_code) VALUES ('TUN', 'TN', 'TUN', '788', '+216');
INSERT INTO ref_country (oct_code, alpha2, alpha3, numeric_code, dial_code) VALUES ('FRA', 'FR', 'FRA', '250', '+33');
INSERT INTO ref_region (oct_code, country_id) SELECT 'SOUSSE_REG', id FROM ref_country WHERE alpha2='TN';
INSERT INTO ref_city (oct_code, region_id) SELECT 'SOUSSE_CITY', id FROM ref_region WHERE oct_code='SOUSSE_REG';

INSERT INTO ref_property_category (oct_code, rental_mode_code) VALUES ('HOTEL_CAT', 'room');
INSERT INTO ref_property_rating (oct_code) VALUES ('4STAR');
INSERT INTO ref_hotel_chain (name) VALUES ('El Mouradi Group');
INSERT INTO ref_board_type (oct_code) VALUES ('ALL_INCL');

INSERT INTO ref_accommodation (name, city_id, category_id, rating_id)
    SELECT 'El Mouradi Beach', c.id, cat.id, r.id
    FROM ref_city c, ref_property_category cat, ref_property_rating r
    WHERE c.oct_code='SOUSSE_CITY' AND cat.oct_code='HOTEL_CAT' AND r.oct_code='4STAR';

INSERT INTO ref_room_category (oct_code) VALUES ('DOUBLE_ROOM');
INSERT INTO product_accommodation_room (accommodation_id, room_category_id)
    SELECT a.id, rc.id FROM ref_accommodation a, ref_room_category rc
    WHERE a.name='El Mouradi Beach' AND rc.oct_code='DOUBLE_ROOM';

-- ------------------------------------------------------------
-- Vocabulaire vol
-- ------------------------------------------------------------
INSERT INTO ref_airline_company (oct_code, iata_code) VALUES ('TU', 'TU');
INSERT INTO ref_cabin_class (oct_code) VALUES ('ECO');
-- NOTE : pas d'INSERT ref_currency ici -- TND est déjà seedé (avec
-- oct_code renseigné) dans schema-ref-common.sql. Un doublon ici
-- échouait sur la contrainte NOT NULL oct_code, non rattrapée par
-- ON CONFLICT (PostgreSQL vérifie NOT NULL avant la détection de
-- conflit) -- bug signalé et corrigé le 19/07/2026.

-- ------------------------------------------------------------
-- Comptes affiliés
-- ------------------------------------------------------------
INSERT INTO party_account (nature, display_name) VALUES ('organization', 'LADYBIRD TRAVEL TOURS');
INSERT INTO party_account (nature, display_name) VALUES ('organization', 'AUTRE AGENCE TEST');

-- Groupe d'affiliés -- Party, dimension 'pricing' (types réels seedés
-- 24/07 : contracting/pricing/collection/reporting ; 'commercial' retiré)
INSERT INTO party_account_group (group_type_code, name) VALUES ('pricing', 'Groupe Amicale 1');
INSERT INTO party_account_group_member (account_id, group_id)
    SELECT a.id, g.id FROM party_account a, party_account_group g
    WHERE a.display_name='AUTRE AGENCE TEST' AND g.name='Groupe Amicale 1';

-- Groupe de pays -- ref_static (ref_country_group), pour le ciblage vol
INSERT INTO ref_country_group (name) VALUES ('Europe test');
INSERT INTO ref_country_group_member (group_id, country_id)
    SELECT g.id, c.id FROM ref_country_group g, ref_country c
    WHERE g.name='Europe test' AND c.alpha2='FR';

-- content_provider (myGO Octasoft = agrégateur direct, Hotelbeds = API externe)
INSERT INTO content_provider (oct_code, name) VALUES ('MYGO_OCT', 'myGO Octasoft');
INSERT INTO content_provider (oct_code, name) VALUES ('HBEDS', 'Hotelbeds');


-- ============================================================
-- RÈGLE 1 (marge, hôtel) : reproduit la capture legacy -- marge 207
-- (montant) sur El Mouradi Beach, source achat myGO Octasoft, ciblant
-- Groupe Amicale 1 ET LADYBIRD TRAVEL TOURS, source vente API OUT + WEB.
-- ============================================================
INSERT INTO pricing_rule (rule_nature_code, service_type_code, label, reservation_date_from, reservation_date_to)
    VALUES ('margin', 'hotel', 'Test capture legacy -- El Mouradi Beach', NULL, NULL)
    RETURNING id \gset rule1_

INSERT INTO pricing_margin_detail (rule_id, value_type_code, value)
    VALUES (:'rule1_id', 'amount', 207.000);

INSERT INTO pricing_rule_hotel_criteria (rule_id, stay_duration_min, stay_duration_max)
    VALUES (:'rule1_id', 1, 99);

INSERT INTO pricing_rule_hotel_target_accommodation (rule_id, accommodation_id)
    SELECT :'rule1_id', id FROM ref_accommodation WHERE name='El Mouradi Beach';

INSERT INTO pricing_rule_hotel_board_type (rule_id, board_type_id)
    SELECT :'rule1_id', id FROM ref_board_type WHERE oct_code='ALL_INCL';

INSERT INTO pricing_rule_target_account (rule_id, party_account_id)
    SELECT :'rule1_id', id FROM party_account WHERE display_name='LADYBIRD TRAVEL TOURS';

INSERT INTO pricing_rule_target_group (rule_id, group_id)
    SELECT :'rule1_id', id FROM party_account_group WHERE name='Groupe Amicale 1';

INSERT INTO pricing_rule_purchase_source (rule_id, content_provider_id)
    SELECT :'rule1_id', id FROM content_provider WHERE oct_code='MYGO_OCT';

INSERT INTO pricing_rule_sale_channel (rule_id, channel_code) VALUES (:'rule1_id', 'api_out');
INSERT INTO pricing_rule_sale_channel (rule_id, channel_code) VALUES (:'rule1_id', 'web');

-- Log de création (simule l'audit trail, cf. capture "Historique")
INSERT INTO pricing_rule_log (rule_id, event_type, field_changes, actor_account_id)
    SELECT :'rule1_id', 'created',
           '[{"field":"achat","before":null,"after":"660.000"},{"field":"marge","before":null,"after":"207.000"}]'::jsonb,
           id
    FROM party_account WHERE display_name='AUTRE AGENCE TEST';

-- Modification (simule la 2e ligne de la capture : marge 207 -> 497)
UPDATE pricing_margin_detail SET value = 497.000 WHERE rule_id = :'rule1_id';
INSERT INTO pricing_rule_log (rule_id, event_type, field_changes)
    VALUES (:'rule1_id', 'updated', '[{"field":"marge","before":"207.000","after":"497.000"}]'::jsonb);


-- ============================================================
-- RÈGLE 2 (marge, hôtel) : règle générale (sans ciblage précis), même
-- hôtel, créée APRÈS la règle 1 -- vérifie le tie-break created_at
-- (confirmé : la règle générale, plus récente, écrase silencieusement
-- la règle ciblée, même pour LADYBIRD).
-- ============================================================
INSERT INTO pricing_rule (rule_nature_code, service_type_code, label)
    VALUES ('margin', 'hotel', 'Règle générale El Mouradi -- tous affiliés')
    RETURNING id \gset rule2_

INSERT INTO pricing_margin_detail (rule_id, value_type_code, value)
    VALUES (:'rule2_id', 'percentage', 10.00);

INSERT INTO pricing_rule_hotel_target_accommodation (rule_id, accommodation_id)
    SELECT :'rule2_id', id FROM ref_accommodation WHERE name='El Mouradi Beach';
-- Pas de ciblage affilié -- s'applique à tout le monde (vide = pas de restriction)
-- Pas de source achat/vente -- s'applique à tout canal/source


-- ============================================================
-- RÈGLE 3 (commission, hôtel) : commission de 5% reversée à AUTRE
-- AGENCE TEST sur les ventes LADYBIRD -- vérifie que marge et
-- commission cohabitent sans conflit structurel (et que la FK
-- composite rule_nature_code empêche tout mélange).
-- ============================================================
INSERT INTO pricing_rule (rule_nature_code, service_type_code, label)
    VALUES ('commission', 'hotel', 'Commission LADYBIRD -> AUTRE AGENCE TEST')
    RETURNING id \gset rule3_

INSERT INTO pricing_commission_detail (rule_id, value_type_code, value, beneficiary_party_account_id)
    SELECT :'rule3_id', 'percentage', 5.00, id FROM party_account WHERE display_name='AUTRE AGENCE TEST';

INSERT INTO pricing_rule_target_account (rule_id, party_account_id)
    SELECT :'rule3_id', id FROM party_account WHERE display_name='LADYBIRD TRAVEL TOURS';


-- ============================================================
-- RÈGLE 4 (marge, hôtel) : granularité fine -- une seule chambre, un
-- seul jour -- condition posée pour la future grille tarifaire.
-- ============================================================
INSERT INTO pricing_rule (rule_nature_code, service_type_code, label)
    VALUES ('margin', 'hotel', 'Cellule grille -- 1 chambre, 1 jour')
    RETURNING id \gset rule4_

INSERT INTO pricing_margin_detail (rule_id, value_type_code, value)
    VALUES (:'rule4_id', 'amount', 15.500);

INSERT INTO pricing_rule_hotel_criteria (rule_id, stay_date_from, stay_date_to)
    VALUES (:'rule4_id', '2026-08-15', '2026-08-15');

INSERT INTO pricing_rule_hotel_room (rule_id, room_id)
    SELECT :'rule4_id', id FROM product_accommodation_room LIMIT 1;


-- ============================================================
-- RÈGLE 5 (marge, vol/billetterie) : reproduit la capture "Ajout d'un
-- nouvel élément" (billetterie) -- pays départ précis, groupe de pays
-- arrivée, compagnie, classe, dates de départ, intervalle de prix
-- billet, marge éclatée par passager (3 colonnes).
-- ============================================================
INSERT INTO pricing_rule (rule_nature_code, service_type_code, label)
    VALUES ('margin', 'flight', 'Test billetterie -- TUN vers Europe, Tunisair, éco')
    RETURNING id \gset rule5_

INSERT INTO pricing_rule_flight_criteria (rule_id, departure_date_from, departure_date_to, ticket_price_from, ticket_price_to, ticket_price_currency_code)
    VALUES (:'rule5_id', '2026-09-01', '2026-12-31', 200.000, 800.000, 'TND');

INSERT INTO pricing_rule_flight_departure_country (rule_id, country_id)
    SELECT :'rule5_id', id FROM ref_country WHERE alpha2='TN';

INSERT INTO pricing_rule_flight_arrival_country_group (rule_id, country_group_id)
    SELECT :'rule5_id', id FROM ref_country_group WHERE name='Europe test';

INSERT INTO pricing_rule_flight_airline (rule_id, airline_company_id)
    SELECT :'rule5_id', id FROM ref_airline_company WHERE oct_code='TU';

INSERT INTO pricing_rule_flight_cabin_class (rule_id, cabin_class_id)
    SELECT :'rule5_id', id FROM ref_cabin_class WHERE oct_code='ECO';

-- Marge éclatée par passager -- 3 colonnes, une seule ligne (adulte 8%, enfant 5%, bébé 0%)
INSERT INTO pricing_margin_detail (rule_id, value_type_code, value, value_child, value_infant)
    VALUES (:'rule5_id', 'percentage', 8.00, 5.00, 0.00);
