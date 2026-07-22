-- ============================================================
-- Module         : Pricing — marges de vente (pricing_)
-- Objet          : Moteur de règles de marge/commission CONDITIONNELLES,
--                   appliqué PAR-DESSUS un prix d'achat quel qu'il soit
--                   (peu importe sa source -- legacy aujourd'hui, futur
--                   Contracting plus tard). NE STOCKE, NE SAISIT, NI NE
--                   CALCULE AUCUN PRIX D'ACHAT.
-- Version        : 1.0 - Brouillon initial, issu de la session du
--                   19/07/2026. Hôtel confirmé sur écrans legacy réels
--                   (marge + commission). Vol confirmé sur principe
--                   (pays départ/arrivée/compagnie/classe), structure
--                   improvisée. Transfert/Spa/Visa/Bus/Location voiture/
--                   Maritime : IMPROVISÉS, à reconfronter explicitement
--                   à la conception du futur module Contracting (voir
--                   note en fin de fichier).
-- Date           : 2026-07-19
-- Réfs           : ADR-002 (logique métier hors DB), ADR-004 (isolation
--                   mono-tenant), ADR-005 (soft delete sélectif -- ICI
--                   NON APPLIQUÉ, voir note), ADR-010 (PostgreSQL 16),
--                   ADR-017 (pattern interface/stub), ADR-018 (BIGINT
--                   identity + public_id)
-- Dépend de      : party_account (party_, figé), ref_country/ref_region/
--                   ref_accommodation/ref_hotel_chain/ref_board_type/
--                   ref_airline_company/ref_cabin_class (ref_static,
--                   figé), content_provider (ref_static, figé),
--                   booking_service_type/booking_channel (booking_,
--                   figé), product_accommodation_room/
--                   product_vehicle_model/product_transfer_vehicle_category/
--                   product_spa_treatment/product_bus_model (product_,
--                   figé)
-- Hors périmètre (voir sujets-reportes.md) : tout prix d'achat (futur
--   Contracting) ; plafond/solde par compte (SolvencyCheckerInterface --
--   à trancher séparément, PAS construit ici) ; segmentation commerciale
--   fine (tags), distincte du "groupe d'affiliés" léger construit ici ;
--   ciblage structurel B2B/B2C (délibérément écarté en session, voir
--   modele-conceptuel-pricing.md)
-- ============================================================
-- Tables legacy consultées comme LISTE DE FONCTIONNALITÉS, jamais comme
-- gabarit structurel (principe directeur 00-INDEX.md) : écrans "Tarif
-- Arrangements" / "Politique Enfants" / "Réductions Chambres" (module
-- Contracting legacy -- PAS reproduits ici, le couplage achat+marge
-- qu'ils montrent est explicitement rejeté, voir modele-conceptuel-
-- pricing.md) ; écran de règle de marge hôtel (module Pricing legacy --
-- celui-là EST la matière première directe de ce schéma).
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============================================================
-- SECTION 0 — RÉFÉRENTIELS INTERNES DU MODULE
-- ============================================================

-- ------------------------------------------------------------
-- pricing_rule_nature : deux natures de règle bien distinctes,
-- confirmées en session -- jamais fusionnées dans la même table de
-- détail, même si elles partagent la même base de critères (pricing_rule).
--   'margin'     : impacte le prix de vente (ajouté au prix d'achat).
--   'commission' : redistribue une marge DÉJÀ fixée vers un tiers
--                  bénéficiaire, sans changer le prix de vente --
--                  cohérent avec booking_settlement (Règlements/Booking)
--                  qui redivise déjà la marge entre bénéficiaires.
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_nature (
    code        VARCHAR(20) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO pricing_rule_nature (code, sort_order) VALUES
    ('margin',     0),
    ('commission', 1);

COMMENT ON TABLE pricing_rule_nature IS 'Deux natures de règle : margin (fixe le prix de vente par-dessus l''achat) vs commission (redistribue une marge déjà fixée vers un bénéficiaire tiers, sans toucher le prix de vente). Jamais fusionnées : voir pricing_margin_detail / pricing_commission_detail.';

-- ------------------------------------------------------------
-- pricing_value_type : nature du chiffre porté par une règle
-- (pourcentage ou montant), réutilisé par marge ET commission.
-- ------------------------------------------------------------
CREATE TABLE pricing_value_type (
    code        VARCHAR(20) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO pricing_value_type (code, sort_order) VALUES
    ('percentage', 0),
    ('amount',     1);

COMMENT ON TABLE pricing_value_type IS 'Nature du chiffre porté par une règle de marge/commission -- pourcentage ou montant fixe.';

-- ------------------------------------------------------------
-- NOTE : pas de table pricing_affiliate_group ici -- relocalisé dans
-- Party (party_account_group/party_account_group_member, cf. diff
-- party-account-group-extension.diff, 19/07/2026, 3e relecture) --
-- ferme le point 4 de sujets-reportes.md, resté en implémentation
-- reportée depuis la conception initiale de Party. Même raisonnement
-- que pour ref_country_group : concept de portée générale (regroupement
-- de comptes), potentiellement réutile pour du reporting/statistiques,
-- sans rien de spécifique à la marge -- ne doit pas dépendre de Pricing
-- ni être dupliqué. Utilisé plus bas par pricing_rule_target_group.
-- ------------------------------------------------------------



-- ------------------------------------------------------------
-- NOTE : pas de table pricing_passenger_type ni pricing_country_group
-- ici -- voir corrections du 19/07/2026 (2e relecture) :
--   - L'éclatement marge/commission par passager (adulte/enfant/bébé)
--     est porté par 3 colonnes directement sur pricing_margin_detail/
--     pricing_commission_detail (section 1), pas par une table de
--     référence + éclatement en lignes -- ensemble fixe et fermé,
--     3 colonnes nommées suffisent (plus simple, cohérent avec le
--     principe anti-EAV : l'EAV rejeté porte sur des attributs
--     OUVERTS et imprévisibles, pas sur un triplet universellement
--     connu et stable).
--   - Le groupement de pays a été RELOCALISÉ dans ref_static
--     (ref_country_group/ref_country_group_member, cf. diff
--     ref-static-country-group-extension.diff) -- c'est un concept de
--     géographie pure, sans rien de spécifique à la marge,
--     potentiellement réutile par d'autres modules (Visa, reporting
--     géographique...). ref_static est la couche de base : rien n'y
--     doit dépendre de Pricing, donc ce concept ne pouvait pas rester
--     ici. Utilisé plus bas par pricing_rule_flight_*_country_group.
-- ------------------------------------------------------------


-- ============================================================
-- SECTION 1 — NOYAU GÉNÉRIQUE : pricing_rule
-- Porte tout ce qui est commun à TOUS les services (dates réservation,
-- affilié/groupe ciblé, source achat/vente). Les critères SPÉCIFIQUES
-- à un service (hôtel: chambre/arrangement/séjour ; vol: pays/compagnie/
-- classe...) vivent dans des tables compagnons dédiées -- même pattern
-- que booking_service_type/booking_<type>_detail, EAV rejeté par
-- principe (cf. 00-INDEX.md, décisions structurantes).
-- ============================================================
CREATE TABLE pricing_rule (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id           UUID NOT NULL DEFAULT gen_random_uuid(),

    rule_nature_code    VARCHAR(20) NOT NULL REFERENCES pricing_rule_nature(code),
    service_type_code   VARCHAR(30) NOT NULL REFERENCES booking_service_type(code),

    label               VARCHAR(200), -- libellé libre interne pour retrouver la règle en gestion (pas montré au client)

    -- Dates réservation -- UNIVERSEL, applicable à tout service (contrairement
    -- à checkin/séjour/durée séjour, propres à l'hébergement -- voir
    -- pricing_rule_hotel_criteria). NULL de part et d'autre = pas de
    -- contrainte sur cette dimension (simplification actée en session,
    -- remplace la case "Applicable sur toutes les années" du legacy).
    reservation_date_from  DATE,
    reservation_date_to    DATE,

    is_active           BOOLEAN NOT NULL DEFAULT true, -- désactivation temporaire sans supprimer (distinct de la suppression physique, voir pricing_rule_log)

    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by          BIGINT REFERENCES party_account(id),
    updated_by          BIGINT REFERENCES party_account(id),

    CONSTRAINT chk_pricing_rule_reservation_dates
        CHECK (reservation_date_from IS NULL OR reservation_date_to IS NULL OR reservation_date_from <= reservation_date_to)
);

-- UNIQUE (id, rule_nature_code) : automatiquement satisfaite (id est déjà
-- PK), sert uniquement de cible pour les FK composites des tables de
-- détail ci-dessous -- garantit qu'une pricing_margin_detail ne peut
-- JAMAIS référencer une règle de nature 'commission', et inversement.
-- Bug identifié et corrigé en sandbox le 19/07/2026 : rien n'empêchait
-- initialement ce mélange (testé, confirmé, corrigé par ce pattern
-- déclaratif -- pas de trigger, conforme ADR-002).
ALTER TABLE pricing_rule ADD CONSTRAINT uq_pricing_rule_id_nature UNIQUE (id, rule_nature_code);

CREATE UNIQUE INDEX uq_pricing_rule_public_id ON pricing_rule(public_id);
CREATE INDEX idx_pricing_rule_service_type ON pricing_rule(service_type_code);
CREATE INDEX idx_pricing_rule_nature ON pricing_rule(rule_nature_code);
-- Index de résolution : la requête applicative type est "quelles règles
-- actives de telle nature/tel service peuvent matcher, triées par
-- created_at DESC" (tie-break confirmé en session : la plus RÉCEMMENT
-- CRÉÉE qui matche gagne -- pas updated_at, pour ne pas qu'une simple
-- correction de faute de frappe fasse sauter une règle en priorité).
CREATE INDEX idx_pricing_rule_resolution ON pricing_rule(service_type_code, rule_nature_code, is_active, created_at DESC);

COMMENT ON TABLE pricing_rule IS 'Noyau générique d''une règle de marge/commission -- nature, service, dates réservation (universelles), statut actif. Les critères propres à chaque service vivent dans des tables compagnons dédiées (pricing_rule_hotel_criteria, pricing_rule_flight_*...). Résolution de conflit (plusieurs règles matchent) : la plus récemment CRÉÉE gagne (created_at, jamais updated_at) -- logique en Domain (ADR-002), pas en base.';

CREATE TRIGGER trg_pricing_rule_updated_at BEFORE UPDATE ON pricing_rule
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
-- pricing_margin_detail : extension 1-1 de pricing_rule quand
-- rule_nature_code='margin'. Pourcentage OU montant, jamais les deux
-- (value_type_code tranche). Peut être négatif (remise), confirmé en
-- session. Note IMPORTANTE (règle métier, PAS un CHECK ici -- ADR-002) :
-- le legacy impose "montant => marge appliquée PAR CHAMBRE, uniquement
-- source locale" -- contrainte cross-table (dépend de
-- pricing_rule_purchase_source), non exprimable proprement par un
-- simple CHECK PostgreSQL sans trigger métier (interdit par ADR-002).
-- Résolution/validation faite en couche Domain au moment de la
-- sauvegarde de la règle.
-- ------------------------------------------------------------
CREATE TABLE pricing_margin_detail (
    rule_id             BIGINT PRIMARY KEY REFERENCES pricing_rule(id),
    -- Dénormalisé depuis pricing_rule, verrouillé à 'margin' par CHECK.
    -- Sert de cible à la FK composite ci-dessous, qui empêche
    -- structurellement qu'une ligne de marge référence une règle
    -- 'commission'. Immuable (une règle ne change jamais de nature).
    rule_nature_code    VARCHAR(20) NOT NULL DEFAULT 'margin',
    -- Une seule nature (%/montant) pour les 3 colonnes de valeur --
    -- confirmé en session : jamais adulte en % et enfant en montant
    -- au sein de la même règle.
    value_type_code     VARCHAR(20) NOT NULL REFERENCES pricing_value_type(code),
    -- 3 colonnes nommées (pas d'éclatement en lignes) : ensemble
    -- adulte/enfant/bébé fixe et fermé, cohérent avec le principe
    -- anti-EAV (l'EAV rejeté porte sur des attributs ouverts et
    -- imprévisibles, pas sur un triplet universel et stable).
    -- value = valeur générale (cas non éclaté, ex. hôtel) OU valeur
    -- adulte (cas éclaté, ex. vol -- "toujours par passager").
    -- value_child/value_infant = NULL si la règle ne s'éclate pas par
    -- passager (contrainte Domain par service, pas de CHECK ici :
    -- ADR-002).
    value               NUMERIC(12,4) NOT NULL,
    value_child         NUMERIC(12,4),
    value_infant        NUMERIC(12,4),
    CONSTRAINT chk_pricing_margin_detail_nature CHECK (rule_nature_code = 'margin'),
    CONSTRAINT fk_pricing_margin_detail_rule_nature FOREIGN KEY (rule_id, rule_nature_code) REFERENCES pricing_rule(id, rule_nature_code)
);

COMMENT ON TABLE pricing_margin_detail IS 'Détail marge -- pourcentage ou montant (value_type_code, unique pour les 3 colonnes), peut être négatif (remise, confirmé en session). value/value_child/value_infant : 3 colonnes nommées pour couvrir le cas éclaté par passager (vol, "toujours par passager" -- confirmé session billetterie 19/07) sans reproduire un pattern EAV (ensemble fixe et fermé, pas d''attributs ouverts). value_child/value_infant NULL si la règle ne s''éclate pas (ex. hôtel). Contrainte "montant => par chambre, source locale uniquement" documentée mais NON enforced en base (cross-table, logique Domain, ADR-002). rule_nature_code+FK composite empêche structurellement le mélange avec une règle "commission" (bug identifié et corrigé en sandbox 19/07/2026).';

-- ------------------------------------------------------------
-- pricing_commission_detail : extension 1-1 de pricing_rule quand
-- rule_nature_code='commission'. Porte en plus le bénéficiaire (vers
-- qui la commission est reversée) -- absent de la marge, qui ne
-- redistribue à personne, elle fixe juste le prix de vente.
-- ------------------------------------------------------------
CREATE TABLE pricing_commission_detail (
    rule_id                       BIGINT PRIMARY KEY REFERENCES pricing_rule(id),
    -- Symétrique de pricing_margin_detail.rule_nature_code, verrouillé
    -- à 'commission'.
    rule_nature_code              VARCHAR(20) NOT NULL DEFAULT 'commission',
    value_type_code               VARCHAR(20) NOT NULL REFERENCES pricing_value_type(code),
    -- Symétrique de pricing_margin_detail -- 3 colonnes, construit par
    -- prudence/symétrie, NON confirmé en session pour la commission
    -- (seule la marge vol a été confirmée éclatée par passager).
    value                         NUMERIC(12,4) NOT NULL,
    value_child                   NUMERIC(12,4),
    value_infant                  NUMERIC(12,4),
    beneficiary_party_account_id  BIGINT NOT NULL REFERENCES party_account(id), -- qui touche la commission (agent, sous-agence, affilié...)
    CONSTRAINT chk_pricing_commission_detail_nature CHECK (rule_nature_code = 'commission'),
    CONSTRAINT fk_pricing_commission_detail_rule_nature FOREIGN KEY (rule_id, rule_nature_code) REFERENCES pricing_rule(id, rule_nature_code)
);

CREATE INDEX idx_pricing_commission_detail_beneficiary ON pricing_commission_detail(beneficiary_party_account_id);

COMMENT ON TABLE pricing_commission_detail IS 'Détail commission -- pourcentage ou montant, prélevé sur une marge déjà fixée par ailleurs, reversé à beneficiary_party_account_id. Ne change jamais le prix de vente (contrairement à pricing_margin_detail). value/value_child/value_infant construit par symétrie avec pricing_margin_detail, NON confirmé en session pour la commission. rule_nature_code+FK composite empêche structurellement le mélange avec une règle "margin" (bug identifié et corrigé en sandbox 19/07/2026).';


-- ============================================================
-- SECTION 2 — CIBLAGE AFFILIÉ (commun à tous les services)
-- Confirmé sur écran réel : une règle peut cibler PLUSIEURS comptes
-- précis ET PLUSIEURS groupes simultanément, combinés en OR (matche
-- si le compte de la résa est dans la liste OU membre d'un groupe listé).
-- Absence totale de lignes dans les deux tables = règle générale, sans
-- restriction d'affilié (cohérent avec le principe "vide = pas de
-- contrainte" déjà appliqué aux dates).
-- ============================================================
CREATE TABLE pricing_rule_target_account (
    rule_id           BIGINT NOT NULL REFERENCES pricing_rule(id),
    party_account_id  BIGINT NOT NULL REFERENCES party_account(id),
    PRIMARY KEY (rule_id, party_account_id)
);

CREATE INDEX idx_pricing_rule_target_account_account ON pricing_rule_target_account(party_account_id);

COMMENT ON TABLE pricing_rule_target_account IS 'Comptes affiliés précis ciblés par la règle (multi-select). Combiné en OR avec pricing_rule_target_group. Vide = pas de restriction sur cette dimension.';

CREATE TABLE pricing_rule_target_group (
    rule_id   BIGINT NOT NULL REFERENCES pricing_rule(id),
    group_id  BIGINT NOT NULL REFERENCES party_account_group(id), -- Party, voir diff party-account-group-extension.diff
    PRIMARY KEY (rule_id, group_id)
);

CREATE INDEX idx_pricing_rule_target_group_group ON pricing_rule_target_group(group_id);

COMMENT ON TABLE pricing_rule_target_group IS 'Groupes d''affiliés ciblés par la règle (multi-select), référence party_account_group (Party). Combiné en OR avec pricing_rule_target_account. Vide = pas de restriction sur cette dimension.';


-- ============================================================
-- SECTION 3 — SOURCE ACHAT / SOURCE VENTE (commun à tous les services)
-- ============================================================

-- ------------------------------------------------------------
-- pricing_rule_purchase_source : "source achat" -- soit un fournisseur
-- de contenu réel (content_provider, référentiel FERMÉ, ref_static),
-- soit un flag "achat direct/local" (contrat saisi manuellement, futur
-- Contracting -- PAS un vrai content_provider, jamais bricolé comme
-- entrée locale dans un référentiel fermé). Exactement un des deux par
-- ligne (CHECK d'exclusivité). Multi-select : plusieurs lignes possibles
-- par règle (ex: Hotelbeds + Webbeds + local, combinés en OR).
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_purchase_source (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    rule_id               BIGINT NOT NULL REFERENCES pricing_rule(id),
    content_provider_id   BIGINT REFERENCES content_provider(id),
    is_local_direct       BOOLEAN NOT NULL DEFAULT false,
    CONSTRAINT chk_pricing_purchase_source_exclusive
        CHECK ((content_provider_id IS NOT NULL AND is_local_direct = false)
            OR (content_provider_id IS NULL AND is_local_direct = true))
);

CREATE UNIQUE INDEX uq_pricing_purchase_source_provider ON pricing_rule_purchase_source(rule_id, content_provider_id) WHERE content_provider_id IS NOT NULL;
CREATE UNIQUE INDEX uq_pricing_purchase_source_local ON pricing_rule_purchase_source(rule_id) WHERE is_local_direct = true;
CREATE INDEX idx_pricing_purchase_source_provider ON pricing_rule_purchase_source(content_provider_id);

COMMENT ON TABLE pricing_rule_purchase_source IS 'Source achat ciblée -- soit un content_provider réel (API), soit is_local_direct=true (contrat direct saisi manuellement, futur Contracting). Jamais les deux (CHECK). Vide = pas de restriction sur cette dimension.';

-- ------------------------------------------------------------
-- pricing_rule_sale_channel : "source vente" -- réutilise booking_channel
-- (déjà figé). ATTENTION signalée en session : booking_channel.api_in/
-- api_out portent une définition inversée par rapport à la terminologie
-- confirmée par l'utilisateur (voir sujets-reportes.md §47, correction
-- actée mais PAS encore appliquée à Booking à la date de cette session --
-- ce module réutilise les codes existants tels quels, la correction se
-- fera par diff sur booking_channel, sans réouverture de ce module).
-- Nationalité/IP/device explicitement écartés : absents du legacy,
-- aucun besoin réel confirmé aujourd'hui (voir sujets-reportes.md §48).
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_sale_channel (
    rule_id       BIGINT NOT NULL REFERENCES pricing_rule(id),
    channel_code  VARCHAR(30) NOT NULL REFERENCES booking_channel(code),
    PRIMARY KEY (rule_id, channel_code)
);

COMMENT ON TABLE pricing_rule_sale_channel IS 'Canal de vente ciblé (multi-select), réutilise booking_channel. Vide = pas de restriction. ATTENTION : booking_channel.api_in/api_out à corriger, voir sujets-reportes.md §47.';


-- ============================================================
-- SECTION 4 — CRITÈRES HÔTEL (pricing_rule_hotel_criteria)
-- CONFIRMÉ sur écrans legacy réels (règle de marge hôtel). Granularité
-- vérifiée : descend jusqu'à UNE SEULE chambre / UN SEUL arrangement /
-- une plage réduite à un seul jour -- condition explicitement posée en
-- session pour permettre, plus tard, une UI de grille tarifaire
-- cellule-par-cellule (cf. discussion sur la future grille façon
-- booking.com) sans reconception.
-- ============================================================
CREATE TABLE pricing_rule_hotel_criteria (
    rule_id             BIGINT PRIMARY KEY REFERENCES pricing_rule(id),

    -- Checkin / séjour / durée séjour -- PROPRES à l'hébergement,
    -- volontairement PAS sur pricing_rule (un vol n'a pas de "séjour").
    checkin_date_from   DATE,
    checkin_date_to     DATE,
    stay_date_from      DATE, -- période couverte par le séjour (distincte du checkin : le séjour peut commencer après le checkin si la règle porte sur une nuit précise du séjour)
    stay_date_to         DATE,
    stay_duration_min    SMALLINT, -- "Dr. Séjour de" -- nombre de nuits minimum
    stay_duration_max    SMALLINT, -- "Dr. Séjour à"

    min_stay             SMALLINT, -- séjour minimum requis pour que la règle s'applique (distinct de stay_duration_min : rejoue le champ "Min Stay" du legacy, condition d'éligibilité plutôt que borne de plage -- à confirmer/fusionner avec Contracting)

    CONSTRAINT chk_pricing_hotel_checkin_dates
        CHECK (checkin_date_from IS NULL OR checkin_date_to IS NULL OR checkin_date_from <= checkin_date_to),
    CONSTRAINT chk_pricing_hotel_stay_dates
        CHECK (stay_date_from IS NULL OR stay_date_to IS NULL OR stay_date_from <= stay_date_to),
    CONSTRAINT chk_pricing_hotel_stay_duration
        CHECK (stay_duration_min IS NULL OR stay_duration_max IS NULL OR stay_duration_min <= stay_duration_max)
);

COMMENT ON TABLE pricing_rule_hotel_criteria IS 'Critères propres à l''hôtel -- checkin/séjour/durée séjour, confirmés sur écran legacy réel. Granularité vérifiée jusqu''au jour unique (condition posée en session pour la future grille tarifaire).';

-- ------------------------------------------------------------
-- Ciblage hôtel/groupe hôtel -- confirmé multi-select combiné (capture
-- "Hôtels / Groupe Hôtels" dans le même champ). Deux tables séparées
-- (hôtel précis vs chaîne), combinées en OR -- vide des deux = tous
-- les hôtels.
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_hotel_target_accommodation (
    rule_id           BIGINT NOT NULL REFERENCES pricing_rule(id),
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    PRIMARY KEY (rule_id, accommodation_id)
);

CREATE TABLE pricing_rule_hotel_target_chain (
    rule_id         BIGINT NOT NULL REFERENCES pricing_rule(id),
    hotel_chain_id  BIGINT NOT NULL REFERENCES ref_hotel_chain(id),
    PRIMARY KEY (rule_id, hotel_chain_id)
);

COMMENT ON TABLE pricing_rule_hotel_target_accommodation IS 'Hôtels précis ciblés (multi-select). Combiné en OR avec pricing_rule_hotel_target_chain. Vide des deux = tous les hôtels.';
COMMENT ON TABLE pricing_rule_hotel_target_chain IS 'Chaînes hôtelières ciblées (multi-select, "Groupe Hôtels" legacy). Combiné en OR avec pricing_rule_hotel_target_accommodation.';

-- ------------------------------------------------------------
-- Granularité chambre -- descend jusqu'à UNE chambre précise (confirmé
-- nécessaire pour la future grille). Vide = toutes les chambres de
-- l'hôtel ciblé.
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_hotel_room (
    rule_id  BIGINT NOT NULL REFERENCES pricing_rule(id),
    room_id  BIGINT NOT NULL REFERENCES product_accommodation_room(id),
    PRIMARY KEY (rule_id, room_id)
);

COMMENT ON TABLE pricing_rule_hotel_room IS 'Chambres précises ciblées (granularité fine, confirmée nécessaire pour la future grille tarifaire). Vide = toutes les chambres.';

-- ------------------------------------------------------------
-- Granularité arrangement (pension/board type) -- descend jusqu'à UN
-- arrangement précis. Vide = tous les arrangements.
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_hotel_board_type (
    rule_id        BIGINT NOT NULL REFERENCES pricing_rule(id),
    board_type_id  BIGINT NOT NULL REFERENCES ref_board_type(id),
    PRIMARY KEY (rule_id, board_type_id)
);

COMMENT ON TABLE pricing_rule_hotel_board_type IS 'Arrangements/pensions précis ciblés (granularité fine). Vide = tous les arrangements.';

-- ------------------------------------------------------------
-- Jours de la semaine -- petit référentiel interne 0-6, pas de table
-- de référence dédiée (concept trivial et universel, contrairement aux
-- autres référentiels du projet qui portent un vrai vocabulaire
-- métier/traduit -- pas de traduction nécessaire ici).
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_hotel_weekday (
    rule_id      BIGINT NOT NULL REFERENCES pricing_rule(id),
    weekday      SMALLINT NOT NULL, -- 0=lundi ... 6=dimanche (convention ISO-8601, à documenter côté Domain)
    PRIMARY KEY (rule_id, weekday),
    CONSTRAINT chk_pricing_hotel_weekday_range CHECK (weekday BETWEEN 0 AND 6)
);

COMMENT ON TABLE pricing_rule_hotel_weekday IS 'Jours de la semaine où la règle s''applique (ex: weekend uniquement). Vide = tous les jours. Convention ISO-8601 (0=lundi), pas de référentiel dédié (concept trivial, pas de traduction).';


-- ============================================================
-- SECTION 5 — CRITÈRES VOL (pricing_rule_flight_*)
-- CONFIRMÉ sur écran legacy réel (billetterie vol, session du 19/07,
-- 2e écran) : pays départ/arrivée (précis et/ou groupe), compagnies,
-- classes, date de départ, marge éclatée par passager (adulte/enfant/
-- bébé), intervalle de prix billet. Point de vigilance déjà identifié :
-- le sous-module Aérien de Product/Catalogue est volontairement réduit
-- à la production PONCTUELLE hors GDS -- la grande majorité des vols
-- réels (GDS live) n'ont AUCUNE fiche product_. Les critères ci-dessous
-- référencent donc ref_airline_company/ref_cabin_class/ref_country
-- DIRECTEMENT (ref_static), jamais une entité product_ -- confirmé
-- nécessaire en session pour que ces règles puissent matcher un vol
-- GDS ordinaire.
-- ============================================================
-- ------------------------------------------------------------
-- pricing_rule_flight_criteria : critères 1-1, confirmés sur écran
-- legacy réel (billetterie vol, session du 19/07) -- date de DÉPART du
-- vol (distincte de reservation_date_from/to, universelle, portée par
-- pricing_rule) et intervalle de prix billet (devise obligatoire, un
-- intervalle n'a de sens que dans une devise donnée).
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_flight_criteria (
    rule_id                BIGINT PRIMARY KEY REFERENCES pricing_rule(id),
    departure_date_from    DATE,
    departure_date_to      DATE,
    ticket_price_from       NUMERIC(12,4),
    ticket_price_to         NUMERIC(12,4),
    ticket_price_currency_code  VARCHAR(3) REFERENCES ref_currency(code),

    CONSTRAINT chk_pricing_flight_departure_dates
        CHECK (departure_date_from IS NULL OR departure_date_to IS NULL OR departure_date_from <= departure_date_to),
    CONSTRAINT chk_pricing_flight_price_range
        CHECK (ticket_price_from IS NULL OR ticket_price_to IS NULL OR ticket_price_from <= ticket_price_to),
    -- Un intervalle de prix sans devise n'a pas de sens -- si l'une des
    -- bornes est renseignée, la devise doit l'être aussi.
    CONSTRAINT chk_pricing_flight_price_currency
        CHECK ((ticket_price_from IS NULL AND ticket_price_to IS NULL) OR ticket_price_currency_code IS NOT NULL)
);

COMMENT ON TABLE pricing_rule_flight_criteria IS 'Critères 1-1 vol -- date de départ (distincte de la date de réservation, universelle) et intervalle de prix billet (devise obligatoire si renseigné). Confirmé sur écran legacy réel (billetterie), session du 19/07.';

-- ------------------------------------------------------------
-- Pays départ/arrivée : pays précis ET/OU groupe de pays, combinés en
-- OR -- même pattern que le ciblage affilié (compte précis ET/OU
-- groupe). Confirmé sur écran legacy réel (billetterie).
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_flight_departure_country (
    rule_id     BIGINT NOT NULL REFERENCES pricing_rule(id),
    country_id  BIGINT NOT NULL REFERENCES ref_country(id),
    PRIMARY KEY (rule_id, country_id)
);

CREATE TABLE pricing_rule_flight_departure_country_group (
    rule_id           BIGINT NOT NULL REFERENCES pricing_rule(id),
    country_group_id  BIGINT NOT NULL REFERENCES ref_country_group(id), -- ref_static, voir diff ref-static-country-group-extension.diff
    PRIMARY KEY (rule_id, country_group_id)
);

CREATE TABLE pricing_rule_flight_arrival_country (
    rule_id     BIGINT NOT NULL REFERENCES pricing_rule(id),
    country_id  BIGINT NOT NULL REFERENCES ref_country(id),
    PRIMARY KEY (rule_id, country_id)
);

CREATE TABLE pricing_rule_flight_arrival_country_group (
    rule_id           BIGINT NOT NULL REFERENCES pricing_rule(id),
    country_group_id  BIGINT NOT NULL REFERENCES ref_country_group(id), -- ref_static, voir diff ref-static-country-group-extension.diff
    PRIMARY KEY (rule_id, country_group_id)
);

CREATE TABLE pricing_rule_flight_airline (
    rule_id             BIGINT NOT NULL REFERENCES pricing_rule(id),
    airline_company_id  BIGINT NOT NULL REFERENCES ref_airline_company(id),
    PRIMARY KEY (rule_id, airline_company_id)
);

CREATE TABLE pricing_rule_flight_cabin_class (
    rule_id         BIGINT NOT NULL REFERENCES pricing_rule(id),
    cabin_class_id  BIGINT NOT NULL REFERENCES ref_cabin_class(id),
    PRIMARY KEY (rule_id, cabin_class_id)
);

COMMENT ON TABLE pricing_rule_flight_departure_country IS 'Pays de départ précis ciblés, référence ref_country directement (pas product_) car la majorité des vols réels sont hors Catalogue (GDS live). Combiné en OR avec pricing_rule_flight_departure_country_group. Confirmé sur écran legacy réel.';
COMMENT ON TABLE pricing_rule_flight_departure_country_group IS 'Groupes de pays de départ ciblés (ex: "Maghreb"). Combiné en OR avec pricing_rule_flight_departure_country. Confirmé sur écran legacy réel.';
COMMENT ON TABLE pricing_rule_flight_arrival_country IS 'Pays d''arrivée précis ciblés, même logique que departure_country.';
COMMENT ON TABLE pricing_rule_flight_arrival_country_group IS 'Groupes de pays d''arrivée ciblés, même logique que departure_country_group.';
COMMENT ON TABLE pricing_rule_flight_airline IS 'Compagnies ciblées, référence ref_airline_company (ref_static) directement. Confirmé sur écran legacy réel.';
COMMENT ON TABLE pricing_rule_flight_cabin_class IS 'Classes cabine ciblées, référence ref_cabin_class (ref_static) directement. Confirmé sur écran legacy réel.';


-- ============================================================
-- SECTION 6 — CRITÈRES AUTRES SERVICES
-- Transfert/Spa/Visa/Bus/Maritime : aucun écran legacy vu, structure
-- minimale de bon sens par analogie avec Hôtel/Vol, IMPROVISÉE, à
-- reconfronter explicitement à la conception du futur module
-- Contracting (cf. "on corrigera une fois on concevra le module
-- Contracting"). Location voiture : CONFIRMÉ en session (19/07, 4e
-- relecture), après vérification explicite du modèle Product/Catalogue
-- réel -- pas improvisé, voir bloc dédié ci-dessous.
-- ============================================================

-- Transfert : catégorie de véhicule (capacité pax/bagages), pas modèle nommé.
CREATE TABLE pricing_rule_transfer_vehicle_category (
    rule_id              BIGINT NOT NULL REFERENCES pricing_rule(id),
    vehicle_category_id  BIGINT NOT NULL REFERENCES product_transfer_vehicle_category(id),
    PRIMARY KEY (rule_id, vehicle_category_id)
);
COMMENT ON TABLE pricing_rule_transfer_vehicle_category IS 'IMPROVISÉ -- catégorie de véhicule transfert ciblée. À reconfronter Contracting.';

-- ------------------------------------------------------------
-- Location voiture -- CONFIRMÉ en session (19/07, 4e relecture), après
-- vérification que Product/Catalogue N'A PAS de couche "catégorie de
-- location" au-dessus de product_vehicle_model (décision actée le
-- 18/07, hypothèse invalidée par capture Hertz réelle -- contrairement
-- au Transfert, qui vend par catégorie). Le concept le plus proche
-- d'une "catégorie" pour la location est product_vehicle_body_type
-- (carrosserie : Citadine/Compacte/SUV...). Modèle précis ET/OU
-- carrosserie, combinés en OR -- même pattern que hôtel/groupe hôtel
-- et pays/groupe pays.
-- ------------------------------------------------------------
CREATE TABLE pricing_rule_car_rental_criteria (
    rule_id               BIGINT PRIMARY KEY REFERENCES pricing_rule(id),
    rental_duration_min   SMALLINT, -- nombre de jours de location minimum
    rental_duration_max   SMALLINT,
    price_from             NUMERIC(12,4), -- intervalle de prix de la location
    price_to               NUMERIC(12,4),
    price_currency_code    VARCHAR(3) REFERENCES ref_currency(code),

    CONSTRAINT chk_pricing_car_rental_duration
        CHECK (rental_duration_min IS NULL OR rental_duration_max IS NULL OR rental_duration_min <= rental_duration_max),
    CONSTRAINT chk_pricing_car_rental_price_range
        CHECK (price_from IS NULL OR price_to IS NULL OR price_from <= price_to),
    CONSTRAINT chk_pricing_car_rental_price_currency
        CHECK ((price_from IS NULL AND price_to IS NULL) OR price_currency_code IS NOT NULL)
);

COMMENT ON TABLE pricing_rule_car_rental_criteria IS 'Critères 1-1 location voiture -- durée de location (jours) et intervalle de prix (devise obligatoire si renseigné). Réutilise reservation_date_from/to de pricing_rule pour l''intervalle de réservation (universel, pas dupliqué ici).';

CREATE TABLE pricing_rule_car_rental_vehicle_model (
    rule_id           BIGINT NOT NULL REFERENCES pricing_rule(id),
    vehicle_model_id  BIGINT NOT NULL REFERENCES product_vehicle_model(id),
    PRIMARY KEY (rule_id, vehicle_model_id)
);

CREATE TABLE pricing_rule_car_rental_body_type (
    rule_id      BIGINT NOT NULL REFERENCES pricing_rule(id),
    body_type_id  BIGINT NOT NULL REFERENCES product_vehicle_body_type(id),
    PRIMARY KEY (rule_id, body_type_id)
);

COMMENT ON TABLE pricing_rule_car_rental_vehicle_model IS 'Modèles de véhicule précis ciblés (ex: "Golf"). Combiné en OR avec pricing_rule_car_rental_body_type. Confirmé en session (19/07).';
COMMENT ON TABLE pricing_rule_car_rental_body_type IS 'Carrosseries ciblées (ex: toutes les Citadines) -- concept le plus proche d''une "catégorie" pour la location (product_vehicle_body_type, Catalogue). Combiné en OR avec pricing_rule_car_rental_vehicle_model. Confirmé en session (19/07).';

-- Spa : centre et/ou soin précis.
CREATE TABLE pricing_rule_spa_center (
    rule_id       BIGINT NOT NULL REFERENCES pricing_rule(id),
    spa_center_id  BIGINT NOT NULL REFERENCES product_spa_center(id),
    PRIMARY KEY (rule_id, spa_center_id)
);
CREATE TABLE pricing_rule_spa_treatment (
    rule_id         BIGINT NOT NULL REFERENCES pricing_rule(id),
    spa_treatment_id  BIGINT NOT NULL REFERENCES product_spa_treatment(id),
    PRIMARY KEY (rule_id, spa_treatment_id)
);
COMMENT ON TABLE pricing_rule_spa_center IS 'IMPROVISÉ -- centre de spa ciblé. À reconfronter Contracting.';
COMMENT ON TABLE pricing_rule_spa_treatment IS 'IMPROVISÉ -- soin précis ciblé. À reconfronter Contracting.';

-- Visa : pays cible du visa (ref_country, réutilisation directe).
CREATE TABLE pricing_rule_visa_country (
    rule_id     BIGINT NOT NULL REFERENCES pricing_rule(id),
    country_id  BIGINT NOT NULL REFERENCES ref_country(id),
    PRIMARY KEY (rule_id, country_id)
);
COMMENT ON TABLE pricing_rule_visa_country IS 'IMPROVISÉ -- pays de destination du visa ciblé. À reconfronter Contracting.';

-- Bus : modèle de bus (produit vendu, réutilisé ligne/ramassage groupé).
CREATE TABLE pricing_rule_bus_model (
    rule_id       BIGINT NOT NULL REFERENCES pricing_rule(id),
    bus_model_id  BIGINT NOT NULL REFERENCES product_bus_model(id),
    PRIMARY KEY (rule_id, bus_model_id)
);
COMMENT ON TABLE pricing_rule_bus_model IS 'IMPROVISÉ -- modèle de bus ciblé. À reconfronter Contracting.';

-- Maritime : AUCUNE entité Product/Catalogue n'existe pour ce service
-- (absent des 8 sous-modules figés le 19/07 -- trou découvert en
-- session Pricing). Pas de table de critère spécifique pour l'instant :
-- une règle 'maritime' ne peut utiliser QUE le noyau générique
-- (pricing_rule + affilié/source achat-vente), aucun critère de service
-- fin possible tant que ce trou n'est pas comblé.
-- Voir sujets-reportes.md §49 pour le signalement.


-- ============================================================
-- SECTION 7 — AUDIT TRAIL (pricing_rule_log)
-- Confirmé en session : snapshot complet avant/après à chaque
-- modification, y compris suppression, avec auteur -- pattern identique
-- à booking_log (event_type/description/metadata JSONB), APPEND-ONLY.
-- rule_id est une FK APPLICATIVE (pas de contrainte FK réelle) : une
-- règle peut être physiquement supprimée (confirmé en session, pas de
-- soft delete ici -- cohérent avec le fait que Booking ne relit jamais
-- les règles a posteriori, seul le résultat déjà résolu au moment de la
-- vente compte, cf. price_breakdown JSONB sur booking). Le log doit
-- donc survivre à la suppression de la règle qu'il documente.
-- ============================================================
CREATE TABLE pricing_rule_log (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    rule_id           BIGINT NOT NULL, -- FK applicative, PAS de contrainte réelle (survit à la suppression de la règle)
    event_type        VARCHAR(30) NOT NULL, -- 'created','updated','activated','deactivated','deleted'
    field_changes     JSONB NOT NULL DEFAULT '[]'::jsonb, -- ex: [{"field":"marge","before":"207.000","after":"497.000"}] -- reproduit le pattern legacy (capture "Historique")
    actor_account_id  BIGINT REFERENCES party_account(id), -- NULL si système/automatique
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_pricing_rule_log_rule ON pricing_rule_log(rule_id, created_at);
CREATE INDEX idx_pricing_rule_log_event_type ON pricing_rule_log(event_type);

COMMENT ON TABLE pricing_rule_log IS 'Audit trail append-only -- snapshot des champs modifiés (avant/après) à chaque création/modification/activation/désactivation/suppression, avec auteur. rule_id est une FK applicative (survit à la suppression physique de la règle, confirmée en session). Pattern identique à booking_log -- 2e occurrence réelle du besoin, candidat à extraction en système de log générique transverse (cf. sujets-reportes.md §44).';


-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. Résolution de règle (Domain, PAS en base, ADR-002) :
--    - Filtrer pricing_rule par service_type_code + rule_nature_code +
--      is_active=true + fenêtre de dates.
--    - Pour chaque candidate, vérifier le OR sur affilié (compte précis
--      OU groupe), le OR sur source achat, le OR sur canal vente, PUIS
--      les critères spécifiques au service (hôtel: chambre/arrangement/
--      séjour/jours semaine ; vol: pays/compagnie/classe...).
--    - Parmi les règles qui matchent TOUS les critères renseignés :
--      prendre celle avec le created_at le plus récent.
--
-- 2. Contrainte "montant => par chambre, source locale uniquement" :
--    validée en Domain à la sauvegarde d'une pricing_margin_detail,
--    jamais en CHECK PostgreSQL (cross-table avec
--    pricing_rule_purchase_source, incompatible avec ADR-002).
--
-- 3. Grille tarifaire façon booking.com (sujet FUTUR, hors périmètre
--    de ce module, évoqué en session) : NE nécessite AUCUNE nouvelle
--    table ici. Une cellule modifiée dans cette future UI = une
--    pricing_rule créée/ajustée avec des critères resserrés au maximum
--    (une chambre, un arrangement, un jour). La colonne "achat" de cette
--    grille lira le futur Contracting, jamais ce module. Condition déjà
--    vérifiée dans ce schéma : la granularité hôtel descend bien jusqu'à
--    l'unité (pricing_rule_hotel_room / _board_type, une seule date via
--    stay_date_from=stay_date_to).
--
-- 4. Trou découvert en session : Maritime n'a AUCUNE entité dans
--    Product/Catalogue (absent des 8 sous-modules figés) -- une règle
--    Pricing sur ce service ne peut utiliser que le noyau générique,
--    aucun critère fin. À combler quand Maritime sera traité côté
--    Catalogue (hors périmètre de cette session).
--
-- 5. SolvencyCheckerInterface / plafond (sujets-reportes.md §25bis) :
--    PAS construit dans cette V1 -- décision de scope prise en cours de
--    session (le plafond est une contrainte de solvabilité par compte/
--    devise, nature différente d'une règle de marge conditionnelle).
--    Reste un sujet ouvert pour une session Finance dédiée.
--
-- 6. Point 43 (sujets-reportes.md, modalités de paiement actif/inactif
--    par service/période) : PAS construit dans cette V1, sujet non
--    traité dans cette session malgré l'intention initiale -- reste
--    ouvert, à confirmer si même famille de critères (probable) lors
--    d'une prochaine session.
-- ============================================================

-- ============================================================
-- BAKED depuis diff-pricing-payment-modality.sql (réouverture ciblée
-- 19/07/2026, ferme sujets-reportes.md §43). Intégré au schéma de base
-- par le chat pilote le 22/07/2026 (le pseudo-diff flottant n'était pas
-- exécutable tel quel -- en-tête de hunk non standard).
-- ============================================================
-- ============================================================
-- Réouverture ciblée documentée (19/07/2026), validée avec le chat
-- pilote -- ferme le point 43 de sujets-reportes.md ("Activation/
-- désactivation des modalités de paiement par service/période").
--
-- Origine : une "modalité de paiement" hôtelière est une combinaison
-- nommée déterminant (1) la répartition acompte/solde entre agence et
-- fournisseur (qui encaisse quoi), (2) au nom de qui la facture hôtel
-- est établie (client ou agence). Le ciblage (hôtel/groupe hôtel,
-- chambre, affilié/groupe affilié, dates réservation, dates arrivée)
-- est ENTIÈREMENT couvert par le moteur de ciblage existant de
-- pricing_rule -- aucune nouvelle table de ciblage créée ici.
-- ============================================================

-- Ajout additif au référentiel de nature existant -- même famille que
-- margin/commission, même garde-fou de non-mélange (FK composite).
INSERT INTO pricing_rule_nature (code, sort_order) VALUES ('payment_modality', 2);

-- ------------------------------------------------------------
-- pricing_payment_party_role : petit référentiel dédié -- les valeurs
-- possibles (agence/fournisseur/client) ne sont PAS des party_role
-- (qui portent des rôles réels de tiers externes vis-à-vis d'un
-- bureau), c'est une bascule interne propre à la modalité de paiement.
-- Table plutôt qu'ENUM, cohérent avec la convention du projet, même
-- pour un petit ensemble fixe (précédent direct : pricing_value_type).
-- ------------------------------------------------------------
CREATE TABLE pricing_payment_party_role (
    code        VARCHAR(20) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO pricing_payment_party_role (code, sort_order) VALUES
    ('agency',   0),
    ('supplier', 1),
    ('client',   2);

COMMENT ON TABLE pricing_payment_party_role IS 'Bascule interne agence/fournisseur/client utilisée par pricing_payment_modality_detail -- distinct de party_role (rôles de tiers externes). ''client'' n''est valide que pour invoiced_to_code (facturation), jamais pour un collecteur acompte/solde (CHECK dédié).';

-- ------------------------------------------------------------
-- pricing_payment_modality_detail : même pattern FK composite que
-- pricing_margin_detail/pricing_commission_detail -- empêche
-- structurellement le mélange avec margin/commission (même garde-fou
-- déjà validé 2 fois en sandbox, voir sujets-reportes.md §53).
--
-- Répartition modélisée comme UN SEUL pourcentage d'acompte (le solde
-- = 100 - acompte, pas de redondance) + un collecteur pour chacune des
-- deux jambes (agence ou fournisseur, jamais client -- CHECK dédié).
-- deposit_percentage=100 est un cas valide (tout payé d'avance à un
-- seul collecteur, pas de jambe solde réelle) -- balance_collector_code
-- reste néanmoins NOT NULL pour rester simple (pas de branchement
-- conditionnel), sa valeur est alors non significative pour le Domain.
-- ------------------------------------------------------------
CREATE TABLE pricing_payment_modality_detail (
    rule_id                 BIGINT PRIMARY KEY REFERENCES pricing_rule(id),
    -- Dénormalisé depuis pricing_rule, verrouillé à 'payment_modality'
    -- par CHECK -- même garde-fou que margin/commission.
    rule_nature_code        VARCHAR(20) NOT NULL DEFAULT 'payment_modality',

    label                   VARCHAR(200) NOT NULL, -- libellé de la modalité (ex: "Avance agence + solde hôtel")

    deposit_percentage      NUMERIC(5,2) NOT NULL, -- % du total qui constitue l'acompte, le reste = solde
    deposit_collector_code  VARCHAR(20) NOT NULL REFERENCES pricing_payment_party_role(code),
    balance_collector_code  VARCHAR(20) NOT NULL REFERENCES pricing_payment_party_role(code),

    invoiced_to_code        VARCHAR(20) NOT NULL REFERENCES pricing_payment_party_role(code), -- au nom de qui la facture hôtel est établie

    CONSTRAINT chk_pricing_payment_modality_nature CHECK (rule_nature_code = 'payment_modality'),
    CONSTRAINT fk_pricing_payment_modality_rule_nature FOREIGN KEY (rule_id, rule_nature_code) REFERENCES pricing_rule(id, rule_nature_code),
    CONSTRAINT chk_pricing_payment_modality_percentage CHECK (deposit_percentage > 0 AND deposit_percentage <= 100),
    -- Les collecteurs acompte/solde ne peuvent être que agence ou
    -- fournisseur, jamais client (le client est celui qui PAIE, pas un
    -- collecteur -- CHECK dédié, distinct du domaine élargi de la
    -- table pricing_payment_party_role).
    CONSTRAINT chk_pricing_payment_modality_deposit_collector CHECK (deposit_collector_code IN ('agency', 'supplier')),
    CONSTRAINT chk_pricing_payment_modality_balance_collector CHECK (balance_collector_code IN ('agency', 'supplier')),
    -- La facture est établie au nom du client ou de l'agence, jamais
    -- du fournisseur (ce serait la facture D'ACHAT, hors périmètre --
    -- Pricing ne touche jamais l'achat).
    CONSTRAINT chk_pricing_payment_modality_invoiced_to CHECK (invoiced_to_code IN ('client', 'agency'))
);

COMMENT ON TABLE pricing_payment_modality_detail IS 'Modalité de paiement hôtelière -- répartition acompte/solde entre agence et fournisseur (qui encaisse quoi) + au nom de qui la facture hôtel est établie. Le ciblage (hôtel/chambre/affilié/dates) est entièrement porté par le moteur pricing_rule existant, aucun ciblage propre ici. rule_nature_code+FK composite empêche structurellement le mélange avec margin/commission (même garde-fou déjà validé en sandbox, sujets-reportes.md §53).';

-- ------------------------------------------------------------
-- NOTE HORS PÉRIMÈTRE (confirmé en session) : l'impact texte sur les
-- documents générés (voucher) reste dans le module Documents (déjà
-- figé, Permissions/Franchises/Config) -- pas de FK ajoutée ici. Si un
-- besoin réel émerge de faire varier un template selon la modalité de
-- paiement, la FK potentielle serait document_trigger_rule ->
-- pricing_payment_modality_detail(rule_id), à ajouter côté Documents
-- par réouverture ponctuelle de CE module-là, pas de Pricing.
-- ------------------------------------------------------------
