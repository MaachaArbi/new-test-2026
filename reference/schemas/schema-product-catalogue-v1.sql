-- ============================================================
-- Module         : Product / Catalogue (product_)
-- Objet          : Fiche technique COMMERCIALE de ce qui est vendable
--                   (hébergement, packages/voyages, véhicules...) --
--                   répond à "qu'est-ce que je vends", jamais "qu'est-ce
--                   que c'est" (rôle de ref_static, module figé, jamais
--                   dupliqué ici).
-- Version        : 1.0 - FIGÉ le 19/07/2026. Couvre : Hôtel, Véhicule,
--                   Spa, Visa (figés 18/07) + Transfert, Aérien, Bus,
--                   Plan de sièges (partagé Aérien/Bus), Package (figés
--                   19/07). Guide : aucune table (voir note dédiée en
--                   fin de fichier, avant la partie Package).
-- Date           : 2026-07-18, complété 2026-07-19
-- Dépend de      : ref_static (ref_accommodation, ref_room_category,
--                   ref_board_type, ref_amenity, ref_country, ref_tag,
--                   ref_airline_company, ref_cabin_class -- ces deux
--                   derniers ajoutés par extension additive 19/07, voir
--                   ref-static-airline-cabin-extension.diff),
--                   ref_common (ref_language), booking_service_type
--                   (Booking, figé -- réutilisé par product_package_component)
-- Hors périmètre explicite de cette partie : capacité de la chambre
--   (nb adultes/enfants, lits) ; actif/inactif (rôle du futur module
--   Contracting, qui choisira quelles chambres/pensions sont vendables
--   par période) ; tout contenu web/CMS/SEO (slug, référencement --
--   migre vers une application CMS séparée, hors périmètre de ce projet) ;
--   aucun prix nulle part dans ce module (Pricing/Contracting).
-- ============================================================

-- ============================================================
-- product_accommodation_room : une chambre vendable à un hôtel donné.
-- Extension du "vocabulaire" ref_room_category (référentiel global
-- partagé, déjà figé) avec le contenu commercial propre à CET hôtel :
-- description, photos, aménagements de la chambre elle-même. Distincte
-- des liaisons hôtel<->vocabulaire (amenities/tags/options/thèmes au
-- niveau de l'hôtel entier), qui restent une extension de ref_static
-- (backlog #112), pas de ce module -- test retenu : contenu commercial
-- propre (description/photo) = Catalogue ; simple lien booléen
-- descriptif = ref_static.
-- ============================================================
CREATE TABLE product_accommodation_room (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id         UUID NOT NULL DEFAULT gen_random_uuid(),
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    room_category_id  BIGINT NOT NULL REFERENCES ref_room_category(id),
    surface_sqm       NUMERIC(6,2), -- nullable : legacy confirme "très peu rempli", on construit quand même
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_accommodation_room_public_id ON product_accommodation_room(public_id);
-- Une catégorie de chambre ne peut être sélectionnée qu'une seule fois par hôtel
-- (choix multiselect, sans doublon -- confirmé explicitement en session).
CREATE UNIQUE INDEX uq_product_accommodation_room_accom_category ON product_accommodation_room(accommodation_id, room_category_id);
CREATE INDEX idx_product_accommodation_room_category ON product_accommodation_room(room_category_id);

COMMENT ON TABLE product_accommodation_room IS 'Chambre vendable à un hôtel donné -- sélection multiselect des catégories vendables par hôtel, sans doublon (UNIQUE accommodation_id+room_category_id). Le libellé générique de la catégorie vient de ref_room_category_translation ; la description propre à CETTE chambre dans CET hôtel vit dans product_accommodation_room_translation. Pas de capacité (hors périmètre V1), pas d''actif/inactif (rôle du futur Contracting).';

CREATE TRIGGER trg_product_accommodation_room_updated_at BEFORE UPDATE ON product_accommodation_room
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
CREATE TABLE product_accommodation_room_translation (
    room_id        BIGINT NOT NULL REFERENCES product_accommodation_room(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    description    TEXT, -- un seul champ, pas de scission courte/longue (décision session)
    PRIMARY KEY (room_id, language_code)
);

COMMENT ON TABLE product_accommodation_room_translation IS 'Description commerciale de la chambre, par langue. Nullable : le legacy montre de nombreuses chambres sans description propre (le libellé de catégorie suffit alors).';

-- ------------------------------------------------------------
CREATE TABLE product_accommodation_room_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    room_id        BIGINT NOT NULL REFERENCES product_accommodation_room(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_accommodation_room_photo_room ON product_accommodation_room_photo(room_id);

COMMENT ON TABLE product_accommodation_room_photo IS 'Galerie de la chambre (0-N photos, contrairement au legacy qui n''en portait qu''une). Une table par entité photographiable (pas de table photo générique polymorphe) -- cohérent avec le rejet EAV déjà acté partout dans le projet ; pattern à reconduire pour chaque futur type de produit (véhicule, etc.).';

-- ------------------------------------------------------------
-- Aménagements DE LA CHAMBRE (WiFi, coffre-fort...) -- réutilise le
-- MÊME référentiel ref_amenity que celui utilisé au niveau hôtel
-- entier dans ref_static (backlog #112). Pas de vocabulaire "aménagement
-- de chambre" dupliqué : WiFi reste WiFi, que la ligne pointe vers
-- l'hôtel ou vers la chambre -- seule la cible change.
-- ------------------------------------------------------------
CREATE TABLE product_accommodation_room_amenity (
    room_id     BIGINT NOT NULL REFERENCES product_accommodation_room(id),
    amenity_id  BIGINT NOT NULL REFERENCES ref_amenity(id),
    PRIMARY KEY (room_id, amenity_id)
);

COMMENT ON TABLE product_accommodation_room_amenity IS 'Aménagements propres à cette chambre (WiFi, coffre-fort...), réutilisation de ref_amenity (ref_static, figé). Distinct de la liaison hôtel-entier<->amenity qui vit dans ref_static (portée différente : la chambre est un produit de ce module, l''hôtel entier est descriptif).';


-- ============================================================
-- product_accommodation_board : une pension (arrangement repas)
-- vendable à un hôtel donné. Volontairement minimal (décision session
-- explicite) : pas de photo, pas d'horaires détaillés (varie par
-- période -- premier/second service -- relève du futur Contracting),
-- pas d'actif/inactif (même raison que la chambre).
-- ============================================================
CREATE TABLE product_accommodation_board (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id         UUID NOT NULL DEFAULT gen_random_uuid(),
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    board_type_id     BIGINT NOT NULL REFERENCES ref_board_type(id),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_accommodation_board_public_id ON product_accommodation_board(public_id);
-- Un type de pension ne peut être affecté qu'une seule fois par hôtel
-- (même règle que product_accommodation_room, confirmée explicitement en session).
CREATE UNIQUE INDEX uq_product_accommodation_board_accom_type ON product_accommodation_board(accommodation_id, board_type_id);
CREATE INDEX idx_product_accommodation_board_type ON product_accommodation_board(board_type_id);

COMMENT ON TABLE product_accommodation_board IS 'Pension vendable à un hôtel donné -- un type de pension ne peut être affecté qu''une fois par hôtel (UNIQUE accommodation_id+board_type_id). Volontairement minimal : ni photo ni horaire (varie par période, hors périmètre de la fiche technique -- rôle du futur Contracting), ni actif/inactif (même logique que product_accommodation_room).';

CREATE TRIGGER trg_product_accommodation_board_updated_at BEFORE UPDATE ON product_accommodation_board
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
CREATE TABLE product_accommodation_board_translation (
    board_id       BIGINT NOT NULL REFERENCES product_accommodation_board(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    description    TEXT,
    PRIMARY KEY (board_id, language_code)
);

COMMENT ON TABLE product_accommodation_board_translation IS 'Description commerciale de la pension, par langue. Nullable, comme product_accommodation_room_translation.';


-- ============================================================
-- PARTIE VÉHICULE (location de voiture) -- session du 18/07/2026
-- Dépend de : rien côté ref_static/ref_common sauf ref_language
-- (vocabulaire 100% local à ce projet, jamais fourni par OctaSoft --
-- contrairement à l'hôtel).
-- ============================================================

-- ------------------------------------------------------------
-- Vocabulaire propre au véhicule (tables de référence, pas d'ENUM)
-- ------------------------------------------------------------
CREATE TABLE product_vehicle_brand (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    name        VARCHAR(100) NOT NULL,
    logo_url    VARCHAR(500),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_brand_public_id ON product_vehicle_brand(public_id);
CREATE UNIQUE INDEX uq_product_vehicle_brand_name ON product_vehicle_brand(name);

COMMENT ON TABLE product_vehicle_brand IS 'Marque véhicule (Renault, BMW...). Nom propre, pas de traduction -- même logique que ref_hotel_chain.';

CREATE TRIGGER trg_product_vehicle_brand_updated_at BEFORE UPDATE ON product_vehicle_brand
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
CREATE TABLE product_vehicle_body_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    icon_url    VARCHAR(500),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_body_type_public_id ON product_vehicle_body_type(public_id);

COMMENT ON TABLE product_vehicle_body_type IS 'Carrosserie (Citadine, SUV...). Référentiel 100% local (jamais fourni par OctaSoft) -- pas de oct_code, contrairement aux référentiels ref_static.';

CREATE TRIGGER trg_product_vehicle_body_type_updated_at BEFORE UPDATE ON product_vehicle_body_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_body_type_translation (
    body_type_id   BIGINT NOT NULL REFERENCES product_vehicle_body_type(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(100) NOT NULL,
    PRIMARY KEY (body_type_id, language_code)
);

-- ------------------------------------------------------------
CREATE TABLE product_vehicle_fuel_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_fuel_type_public_id ON product_vehicle_fuel_type(public_id);

COMMENT ON TABLE product_vehicle_fuel_type IS 'Type d''énergie (Essence, Diesel, Électrique, Hybride...). Vrai référentiel table+traduction, décision explicite en session (pas de texte libre comme en legacy).';

CREATE TRIGGER trg_product_vehicle_fuel_type_updated_at BEFORE UPDATE ON product_vehicle_fuel_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_fuel_type_translation (
    fuel_type_id   BIGINT NOT NULL REFERENCES product_vehicle_fuel_type(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(100) NOT NULL,
    PRIMARY KEY (fuel_type_id, language_code)
);

-- ------------------------------------------------------------
CREATE TABLE product_vehicle_transmission_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_transmission_type_public_id ON product_vehicle_transmission_type(public_id);

COMMENT ON TABLE product_vehicle_transmission_type IS 'Boîte de vitesse (Manuelle, Automatique). Vrai référentiel table+traduction.';

CREATE TRIGGER trg_product_vehicle_transmission_type_updated_at BEFORE UPDATE ON product_vehicle_transmission_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_transmission_type_translation (
    transmission_type_id  BIGINT NOT NULL REFERENCES product_vehicle_transmission_type(id),
    language_code          VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                     VARCHAR(100) NOT NULL,
    PRIMARY KEY (transmission_type_id, language_code)
);

-- ------------------------------------------------------------
-- Équipements : deux niveaux (catégorie + équipement), table
-- d'équipements volontairement vide au démarrage -- legacy ne
-- fournissait que la catégorie ("ÉQUIPEMENTS INTÉRIEURS"), à peupler
-- par l'utilisateur.
-- ------------------------------------------------------------
CREATE TABLE product_vehicle_equipment_category (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_equipment_category_public_id ON product_vehicle_equipment_category(public_id);

CREATE TRIGGER trg_product_vehicle_equipment_category_updated_at BEFORE UPDATE ON product_vehicle_equipment_category
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_equipment_category_translation (
    equipment_category_id  BIGINT NOT NULL REFERENCES product_vehicle_equipment_category(id),
    language_code            VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                       VARCHAR(100) NOT NULL,
    PRIMARY KEY (equipment_category_id, language_code)
);

-- ------------------------------------------------------------
CREATE TABLE product_vehicle_equipment (
    id                      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id               UUID NOT NULL DEFAULT gen_random_uuid(),
    equipment_category_id   BIGINT NOT NULL REFERENCES product_vehicle_equipment_category(id),
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_equipment_public_id ON product_vehicle_equipment(public_id);
CREATE INDEX idx_product_vehicle_equipment_category ON product_vehicle_equipment(equipment_category_id);

CREATE TRIGGER trg_product_vehicle_equipment_updated_at BEFORE UPDATE ON product_vehicle_equipment
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_equipment_translation (
    equipment_id   BIGINT NOT NULL REFERENCES product_vehicle_equipment(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(100) NOT NULL,
    PRIMARY KEY (equipment_id, language_code)
);

-- ------------------------------------------------------------
-- Supplément véhicule (GPS, chaise bébé...) -- référentiel SÉPARÉ de
-- ref_supplement (décision explicite en session, contrairement à
-- l'amenity de chambre qui, lui, réutilise ref_amenity).
-- ------------------------------------------------------------
CREATE TABLE product_vehicle_supplement (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    icon_url    VARCHAR(500),
    is_per_day  BOOLEAN NOT NULL DEFAULT false, -- repris de ost_lv_supplement.parJour ; mode de facturation structurel, PAS un montant
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_supplement_public_id ON product_vehicle_supplement(public_id);

COMMENT ON TABLE product_vehicle_supplement IS 'Supplément véhicule (GPS, chaise bébé...). is_per_day : mode de facturation structurel (par jour vs forfait unique), jamais le montant -- cohérent avec ref_supplement qui applique le même principe côté hôtel.';

CREATE TRIGGER trg_product_vehicle_supplement_updated_at BEFORE UPDATE ON product_vehicle_supplement
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_supplement_translation (
    supplement_id      BIGINT NOT NULL REFERENCES product_vehicle_supplement(id),
    language_code      VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                VARCHAR(150) NOT NULL,
    description_short    TEXT,
    description_long      TEXT,
    PRIMARY KEY (supplement_id, language_code)
);


-- ============================================================
-- product_vehicle_model : le modèle vendable lui-même.
-- ============================================================
CREATE TABLE product_vehicle_model (
    id                              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                       UUID NOT NULL DEFAULT gen_random_uuid(),
    brand_id                        BIGINT NOT NULL REFERENCES product_vehicle_brand(id),
    body_type_id                    BIGINT NOT NULL REFERENCES product_vehicle_body_type(id),
    fuel_type_id                    BIGINT NOT NULL REFERENCES product_vehicle_fuel_type(id),
    transmission_type_id            BIGINT NOT NULL REFERENCES product_vehicle_transmission_type(id),
    name                            VARCHAR(150) NOT NULL, -- nom propre du modèle (ex: "C3", "Golf"), pas de traduction -- même logique que product_vehicle_brand.name
    seats_count                     SMALLINT,
    doors_count                     SMALLINT,
    suitcases_count                 SMALLINT,
    trunk_volume_l                  NUMERIC(6,1),
    length_cm                       NUMERIC(6,1),
    width_cm                        NUMERIC(6,1),
    height_cm                       NUMERIC(6,1),
    consumption_urban_l100          NUMERIC(5,2),
    consumption_extra_urban_l100    NUMERIC(5,2),
    consumption_mixed_l100          NUMERIC(5,2),
    created_at                      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_model_public_id ON product_vehicle_model(public_id);
CREATE INDEX idx_product_vehicle_model_brand ON product_vehicle_model(brand_id);
CREATE INDEX idx_product_vehicle_model_body_type ON product_vehicle_model(body_type_id);

COMMENT ON TABLE product_vehicle_model IS 'Modèle vendable. Exclus volontairement : vitesse (bruit legacy jamais exploité), tarif_carburant/prix_acquisition (Pricing/Contracting), stock_voiture (remplacé par le comptage réel de product_vehicle_unit).';

CREATE TRIGGER trg_product_vehicle_model_updated_at BEFORE UPDATE ON product_vehicle_model
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_vehicle_model_translation (
    model_id       BIGINT NOT NULL REFERENCES product_vehicle_model(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    description    TEXT, -- un seul champ, cohérent avec la décision prise pour chambre/pension
    PRIMARY KEY (model_id, language_code)
);

CREATE TABLE product_vehicle_model_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    model_id       BIGINT NOT NULL REFERENCES product_vehicle_model(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_vehicle_model_photo_model ON product_vehicle_model_photo(model_id);

CREATE TABLE product_vehicle_model_equipment (
    model_id      BIGINT NOT NULL REFERENCES product_vehicle_model(id),
    equipment_id  BIGINT NOT NULL REFERENCES product_vehicle_equipment(id),
    PRIMARY KEY (model_id, equipment_id)
);

CREATE TABLE product_vehicle_model_supplement (
    model_id       BIGINT NOT NULL REFERENCES product_vehicle_model(id),
    supplement_id  BIGINT NOT NULL REFERENCES product_vehicle_supplement(id),
    PRIMARY KEY (model_id, supplement_id)
);

COMMENT ON TABLE product_vehicle_model_supplement IS 'Suppléments disponibles pour CE modèle -- restreint par modèle, pas global (décision explicite en session, contrairement à une hypothèse "valable pour tous").';


-- ============================================================
-- product_pickup_location : lieu de prise en charge/restitution.
-- Référentiel PARTAGÉ (décision explicite en session) : la location
-- de voiture ET le futur transfert (et tout futur service du même
-- type) réutiliseront cette même table -- construit une seule fois.
-- Texte libre, pas de rattachement à ref_city (décision explicite,
-- même forme que le legacy ost_lv_lieu_prise_charge).
-- ============================================================
CREATE TABLE product_pickup_location (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id      UUID NOT NULL DEFAULT gen_random_uuid(),
    label          VARCHAR(255) NOT NULL,
    is_own_agency  BOOLEAN NOT NULL DEFAULT false, -- repris du flag "agence" legacy
    latitude       NUMERIC(9,6),
    longitude      NUMERIC(9,6),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_pickup_location_public_id ON product_pickup_location(public_id);

COMMENT ON TABLE product_pickup_location IS 'Lieu de prise en charge/restitution -- référentiel partagé entre tous les services qui en ont besoin (voiture aujourd''hui, transfert et autres plus tard), pas dupliqué par service. Texte libre (label), pas de FK géographique -- décision explicite en session.';

CREATE TRIGGER trg_product_pickup_location_updated_at BEFORE UPDATE ON product_pickup_location
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- ============================================================
-- product_vehicle_unit : véhicule physique précis (plaque,
-- kilométrage, carburant). Réouverture SCOPÉE de la décision du
-- 16/07 ("Stock Management" retiré du périmètre) -- ne couvre QUE le
-- suivi individuel des véhicules de location, pas une gestion
-- d'inventaire/entrepôt générale, qui reste hors périmètre.
-- État courant, MUTABLE, pas d'historique en V1 (limite documentée,
-- décision explicite -- à revoir si un besoin d'audit trail émerge).
-- Champs legacy exclus : code_radio (valeur opérationnelle isolée,
-- douteuse), status_voiture texte libre (doublonnait avec le
-- booléen status -- même anomalie que le doublon chambre/pension
-- hôtel, non recontacté faute de nécessité), condition_location
-- fusionné dans notes.
-- ============================================================
CREATE TABLE product_vehicle_unit (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id            UUID NOT NULL DEFAULT gen_random_uuid(),
    model_id             BIGINT NOT NULL REFERENCES product_vehicle_model(id),
    plate                VARCHAR(50) NOT NULL,
    color                VARCHAR(100),
    service_start_date   DATE,
    current_mileage_km   INTEGER,
    fuel_gauge_state     NUMERIC(3,2), -- fraction 0.00 à 1.00 (réservoir plein = 1.00)
    is_active            BOOLEAN NOT NULL DEFAULT true,
    notes                TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_vehicle_unit_public_id ON product_vehicle_unit(public_id);
CREATE UNIQUE INDEX uq_product_vehicle_unit_plate ON product_vehicle_unit(plate);
CREATE INDEX idx_product_vehicle_unit_model ON product_vehicle_unit(model_id);

COMMENT ON TABLE product_vehicle_unit IS 'Véhicule physique précis (plaque unique). État courant mutable, pas d''historique en V1. Réouverture scopée de la décision "Stock Management hors périmètre" (16/07) -- ne couvre que le suivi individuel du véhicule loué, jamais une gestion d''entrepôt générale.';

CREATE TRIGGER trg_product_vehicle_unit_updated_at BEFORE UPDATE ON product_vehicle_unit
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- ============================================================
-- PARTIE SPA -- session du 18/07/2026
-- Dépend de : ref_city (ref_static), ref_accommodation (ref_static,
-- lien informatif nullable uniquement -- décision explicite : un
-- centre n'est plus obligatoirement rattaché à un hôtel).
-- ============================================================

CREATE TABLE product_spa_care_category (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_spa_care_category_public_id ON product_spa_care_category(public_id);

COMMENT ON TABLE product_spa_care_category IS 'Catégorie de soin : Cure / Soin / Programme(Pack). Déduit de ost_be_produit.type (1/2/3), confirmé par l''utilisateur.';

CREATE TRIGGER trg_product_spa_care_category_updated_at BEFORE UPDATE ON product_spa_care_category
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_spa_care_category_translation (
    care_category_id  BIGINT NOT NULL REFERENCES product_spa_care_category(id),
    language_code     VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name              VARCHAR(100) NOT NULL,
    PRIMARY KEY (care_category_id, language_code)
);


-- ============================================================
-- product_spa_center : lieu spa. Plus de rattachement OBLIGATOIRE à
-- un hôtel (un centre indépendant reste possible, décision maintenue).
-- city_id est l'ancrage géographique principal ; accommodation_id
-- reste nullable mais redevient STRUCTURANT (revirement du 18/07) :
-- sert à afficher, lors de la réservation d'un hôtel, les cures/soins
-- disponibles dans ce même établissement -- avantage commercial
-- explicite, pas une simple mention.
-- ============================================================
CREATE TABLE product_spa_center_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_spa_center_type_public_id ON product_spa_center_type(public_id);

COMMENT ON TABLE product_spa_center_type IS 'Type d''établissement : Thalasso / Spa Thermal / Spa (classification du CENTRE, pas du soin -- distincte de product_spa_care_category). Ajouté suite benchmark concurrent (Thalasseo.com, 18/07/2026) qui distingue ces 3 types en filtre principal.';

CREATE TRIGGER trg_product_spa_center_type_updated_at BEFORE UPDATE ON product_spa_center_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_spa_center_type_translation (
    center_type_id  BIGINT NOT NULL REFERENCES product_spa_center_type(id),
    language_code   VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name            VARCHAR(100) NOT NULL,
    PRIMARY KEY (center_type_id, language_code)
);

CREATE TABLE product_spa_center (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id         UUID NOT NULL DEFAULT gen_random_uuid(),
    name              VARCHAR(255) NOT NULL, -- nom propre, pas de traduction (même logique que product_vehicle_brand)
    center_type_id    BIGINT NOT NULL REFERENCES product_spa_center_type(id),
    city_id           BIGINT NOT NULL REFERENCES ref_city(id),
    accommodation_id  BIGINT REFERENCES ref_accommodation(id), -- nullable (centre indépendant possible), mais STRUCTURANT quand renseigné (cross-sell hôtel -> spa)
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_spa_center_public_id ON product_spa_center(public_id);
CREATE INDEX idx_product_spa_center_type ON product_spa_center(center_type_id);
CREATE INDEX idx_product_spa_center_city ON product_spa_center(city_id);
CREATE INDEX idx_product_spa_center_accommodation ON product_spa_center(accommodation_id);

COMMENT ON TABLE product_spa_center IS 'Centre spa. accommodation_id nullable (un centre peut exister sans hôtel, ex. spa urbain indépendant) mais STRUCTURANT quand renseigné (revirement du 18/07 : sert à afficher les cures/soins disponibles dans l''hôtel au moment de la réservation -- avantage commercial explicite, plus une simple mention informative). city_id reste l''ancrage géographique pour les centres sans hôtel.';

CREATE TRIGGER trg_product_spa_center_updated_at BEFORE UPDATE ON product_spa_center
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_spa_center_translation (
    center_id      BIGINT NOT NULL REFERENCES product_spa_center(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    description    TEXT, -- un seul champ, cohérent avec chambre/pension hôtel
    PRIMARY KEY (center_id, language_code)
);

CREATE TABLE product_spa_center_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    center_id      BIGINT NOT NULL REFERENCES product_spa_center(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_spa_center_photo_center ON product_spa_center_photo(center_id);


-- ============================================================
-- product_spa_treatment : le soin lui-même. Pas de centre_id direct
-- (indépendant du lieu, vendable dans plusieurs centres via la table
-- de jonction ci-dessous -- reprend ost_be_produit_centre).
-- ============================================================
CREATE TABLE product_spa_treatment (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id         UUID NOT NULL DEFAULT gen_random_uuid(),
    care_category_id  BIGINT NOT NULL REFERENCES product_spa_care_category(id),
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_spa_treatment_public_id ON product_spa_treatment(public_id);
CREATE INDEX idx_product_spa_treatment_category ON product_spa_treatment(care_category_id);

COMMENT ON TABLE product_spa_treatment IS 'Soin vendable (massage, cure, pack...). Pas de slug/état actif (CMS et Contracting, hors périmètre -- décisions explicites 18/07).';

CREATE TRIGGER trg_product_spa_treatment_updated_at BEFORE UPDATE ON product_spa_treatment
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_spa_treatment_translation (
    treatment_id   BIGINT NOT NULL REFERENCES product_spa_treatment(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(255) NOT NULL, -- traduit (contrairement à product_spa_center.name, pas un nom propre)
    description    TEXT,
    PRIMARY KEY (treatment_id, language_code)
);

CREATE TABLE product_spa_treatment_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    treatment_id   BIGINT NOT NULL REFERENCES product_spa_treatment(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_spa_treatment_photo_treatment ON product_spa_treatment_photo(treatment_id);

-- ------------------------------------------------------------
-- Composition d'un Pack/Cure : liste ordonnée d'étapes, chaque étape
-- réutilisant le référentiel de soins lui-même (auto-liaison). Décision
-- explicite (18/07) : structuré plutôt que texte libre, pour permettre
-- le comptage/affichage ("3 soins inclus") et la réutilisation d'un
-- même soin dans plusieurs packs sans duplication de texte. Un soin
-- standalone (type Massage) n'a simplement aucune ligne ici.
-- ------------------------------------------------------------
CREATE TABLE product_spa_treatment_component (
    parent_treatment_id     BIGINT NOT NULL REFERENCES product_spa_treatment(id),
    component_treatment_id  BIGINT NOT NULL REFERENCES product_spa_treatment(id),
    display_order           SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (parent_treatment_id, component_treatment_id),
    CONSTRAINT chk_spa_treatment_component_not_self CHECK (parent_treatment_id <> component_treatment_id)
);

COMMENT ON TABLE product_spa_treatment_component IS 'Composition d''un Pack/Cure : étapes ordonnées, chaque étape référence un soin existant. Pas de protection anti-cycle profond (A contient B, B contient A à un niveau plus loin) -- limite documentée, acceptable au volume attendu.';

-- ------------------------------------------------------------
-- product_spa_treatment_center : jonction N-N + durée, qui VARIE par
-- centre (décision explicite 18/07 -- pas une propriété universelle
-- du soin).
-- ------------------------------------------------------------
CREATE TABLE product_spa_treatment_center (
    treatment_id      BIGINT NOT NULL REFERENCES product_spa_treatment(id),
    center_id         BIGINT NOT NULL REFERENCES product_spa_center(id),
    duration_minutes  SMALLINT,
    PRIMARY KEY (treatment_id, center_id)
);

COMMENT ON TABLE product_spa_treatment_center IS 'Un soin vendable dans un centre donné, avec sa durée PROPRE à ce centre (le même soin peut durer différemment selon où il est proposé). Durée nullable (pas toujours renseignée, cohérent avec le reste du legacy).';


-- ============================================================
-- PARTIE VISA -- session du 18/07/2026
-- Aucun export legacy disponible -- conception neuve à partir du
-- besoin métier (capture d'écran d'un concurrent, SafarClic.com) et
-- de l'expérience standard du secteur (iVisa, VisaHQ...).
-- Dépend de : ref_country (ref_static, figé).
-- ============================================================

CREATE TABLE product_visa_entry_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_visa_entry_type_public_id ON product_visa_entry_type(public_id);

COMMENT ON TABLE product_visa_entry_type IS 'Nombre d''entrées autorisées : Mono-entrée / Multi-entrées.';

CREATE TRIGGER trg_product_visa_entry_type_updated_at BEFORE UPDATE ON product_visa_entry_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_visa_entry_type_translation (
    entry_type_id  BIGINT NOT NULL REFERENCES product_visa_entry_type(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(100) NOT NULL,
    PRIMARY KEY (entry_type_id, language_code)
);

-- ------------------------------------------------------------
CREATE TABLE product_visa_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_visa_type_public_id ON product_visa_type(public_id);

COMMENT ON TABLE product_visa_type IS 'Type de visa : Touristique / Affaires / Transit / Étudiant...';

CREATE TRIGGER trg_product_visa_type_updated_at BEFORE UPDATE ON product_visa_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_visa_type_translation (
    visa_type_id   BIGINT NOT NULL REFERENCES product_visa_type(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(100) NOT NULL,
    PRIMARY KEY (visa_type_id, language_code)
);

-- ------------------------------------------------------------
-- Référentiel réutilisable des documents requis -- décision explicite
-- (18/07) : structuré plutôt que texte libre, pour permettre la
-- réutilisation entre visas (le scan de passeport revient presque
-- partout) et l'affichage en checklist côté vente.
-- ------------------------------------------------------------
CREATE TABLE product_visa_document (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_visa_document_public_id ON product_visa_document(public_id);

COMMENT ON TABLE product_visa_document IS 'Document justificatif réutilisable (scan passeport, photo fond blanc, billet retour, réservation hôtel confirmée, justificatif d''hébergement, assurance voyage...). Réutilisé entre visas via product_visa_document_requirement.';

CREATE TRIGGER trg_product_visa_document_updated_at BEFORE UPDATE ON product_visa_document
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_visa_document_translation (
    document_id    BIGINT NOT NULL REFERENCES product_visa_document(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(255) NOT NULL,
    PRIMARY KEY (document_id, language_code)
);


-- ============================================================
-- product_visa : le produit vendu.
-- ============================================================
CREATE TABLE product_visa (
    id                      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id               UUID NOT NULL DEFAULT gen_random_uuid(),
    destination_country_id  BIGINT NOT NULL REFERENCES ref_country(id),
    passport_country_id     BIGINT REFERENCES ref_country(id), -- nullable = valable pour toutes les nationalités (décision explicite 18/07)
    entry_type_id           BIGINT NOT NULL REFERENCES product_visa_entry_type(id),
    visa_type_id            BIGINT NOT NULL REFERENCES product_visa_type(id),
    is_electronic           BOOLEAN NOT NULL DEFAULT true, -- e-Visa (true) vs consulaire physique (false)
    stay_duration_days      SMALLINT NOT NULL,
    processing_delay_days   SMALLINT, -- nullable : délai estimé, pas toujours communiqué
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_visa_public_id ON product_visa(public_id);
CREATE INDEX idx_product_visa_destination ON product_visa(destination_country_id);
CREATE INDEX idx_product_visa_passport ON product_visa(passport_country_id);
CREATE INDEX idx_product_visa_entry_type ON product_visa(entry_type_id);
CREATE INDEX idx_product_visa_visa_type ON product_visa(visa_type_id);

COMMENT ON TABLE product_visa IS 'Visa vendable pour un pays de destination donné. passport_country_id nullable = valable pour toutes les nationalités ; renseigné = règles spécifiques à cette nationalité (documents/durée/délai peuvent différer d''une nationalité à l''autre pour la même destination -- ajouté suite question explicite de l''utilisateur, 18/07). Pas d''actif/inactif (cohérent avec le reste du module -- rôle du futur Contracting). Pas de prix (Pricing).';

CREATE TRIGGER trg_product_visa_updated_at BEFORE UPDATE ON product_visa
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_visa_translation (
    visa_id          BIGINT NOT NULL REFERENCES product_visa(id),
    language_code    VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name             VARCHAR(255) NOT NULL, -- ex: "E Visa Vietnam 90 Jours Multi Sorties"
    conditions_text  TEXT, -- le contenu de l'onglet "Condition" de la capture (disclaimers, notes)
    PRIMARY KEY (visa_id, language_code)
);

CREATE TABLE product_visa_document_requirement (
    visa_id      BIGINT NOT NULL REFERENCES product_visa(id),
    document_id  BIGINT NOT NULL REFERENCES product_visa_document(id),
    PRIMARY KEY (visa_id, document_id)
);

COMMENT ON TABLE product_visa_document_requirement IS 'Documents requis pour CE visa précis -- remplace la liste à puces en texte libre de la capture SafarClic par une liste structurée et réutilisable.';

-- ============================================================
-- PARTIE GUIDE -- session du 19/07/2026
-- Aucune table dans ce module. Confirmé explicitement par l'utilisateur :
-- pas de catalogue de guides à choisir (contrairement à l'hôtel/véhicule/
-- spa/visa) -- juste une prestation qui se facture. Seule action liée à
-- ce sous-module : ajouter la ligne 'guide' dans booking_service_type
-- (Booking, figé) -- ajout de donnée, pas réouverture structurelle.
-- Si un besoin de vivier de guides (langues parlées, etc.) émerge un
-- jour, il relève de Party (un guide est un tiers avec un rôle), pas de
-- Product/Catalogue.
-- ============================================================


-- ============================================================
-- PARTIE TRANSFERT -- session du 19/07/2026
-- Vente par CATÉGORIE de véhicule (Berline/Monospace/Minibus...), pas
-- par modèle/marque comme la location -- le client choisit une classe,
-- pas "Peugeot 308" (décision explicite, différent de product_vehicle_model).
-- Équipements : RÉUTILISE product_vehicle_equipment (décision explicite
-- en session, contrairement à product_vehicle_supplement qui avait
-- divergé de ref_supplement).
-- Pas de lieu de prise en charge en Catalogue : un transfert se
-- commande sur une adresse/lat-long libre donnée par le client, jamais
-- un choix dans une liste prédéfinie -- product_pickup_location (comptoirs
-- fixes de location) ne s'applique PAS ici, corrige une hypothèse
-- notée à tort dans sujets-reportes.md. Le point A/B est un détail de
-- réservation (Booking), pas un produit catalogue.
-- Privé/partagé retiré de Catalogue (décision explicite) : relève de
-- l'offre commerciale (futur Contracting), pas une propriété
-- structurelle de la catégorie de véhicule.
-- ============================================================
CREATE TABLE product_transfer_vehicle_category (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    capacity_pax       SMALLINT NOT NULL, -- hors conducteur
    capacity_luggage   SMALLINT,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_product_transfer_vehicle_category_capacity CHECK (capacity_pax > 0)
);

CREATE UNIQUE INDEX uq_product_transfer_vehicle_category_public_id ON product_transfer_vehicle_category(public_id);

COMMENT ON TABLE product_transfer_vehicle_category IS 'Catégorie de véhicule vendable en transfert (Berline/Monospace/Minibus...) -- vente par classe, pas par modèle nommé (contrairement à la location, product_vehicle_model). capacity_pax hors conducteur. Pas de lieu de prise en charge, pas de privé/partagé (voir en-tête de section).';

CREATE TRIGGER trg_product_transfer_vehicle_category_updated_at BEFORE UPDATE ON product_transfer_vehicle_category
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_transfer_vehicle_category_translation (
    category_id    BIGINT NOT NULL REFERENCES product_transfer_vehicle_category(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL, -- ex: "Berline confort 3 pax"
    description    TEXT,
    PRIMARY KEY (category_id, language_code)
);

CREATE TABLE product_transfer_vehicle_category_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    category_id    BIGINT NOT NULL REFERENCES product_transfer_vehicle_category(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_transfer_vehicle_category_photo_cat ON product_transfer_vehicle_category_photo(category_id);

CREATE TABLE product_transfer_vehicle_category_equipment (
    category_id   BIGINT NOT NULL REFERENCES product_transfer_vehicle_category(id),
    equipment_id  BIGINT NOT NULL REFERENCES product_vehicle_equipment(id),
    PRIMARY KEY (category_id, equipment_id)
);

COMMENT ON TABLE product_transfer_vehicle_category_equipment IS 'Équipements de cette catégorie de transfert (clim, frigo, wifi...) -- réutilisation explicite de product_vehicle_equipment (même nature d''objet que pour la location), pas de référentiel dupliqué.';


-- ============================================================
-- PARTIE AÉRIEN -- session du 19/07/2026
-- Périmètre volontairement réduit : production PONCTUELLE de
-- billetterie uniquement (cas normal = GDS live, sans fiche produit).
-- Compagnie aérienne = ref_static (descriptif, voir
-- ref-static-airline-cabin-extension.sql) ; flotte/type d'appareil =
-- Catalogue (contenu commercial, ce qu'on vend avec).
-- Dépend de : ref_airline_company (ref_static, extension 19/07).
-- ============================================================
CREATE TABLE product_airline_aircraft_type (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id           UUID NOT NULL DEFAULT gen_random_uuid(),
    airline_company_id  BIGINT NOT NULL REFERENCES ref_airline_company(id),
    name                VARCHAR(150) NOT NULL, -- nom propre (ex: "Airbus A320"), pas de traduction -- même logique que product_vehicle_brand.name
    total_capacity      SMALLINT NOT NULL,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_product_airline_aircraft_type_capacity CHECK (total_capacity > 0)
);

CREATE UNIQUE INDEX uq_product_airline_aircraft_type_public_id ON product_airline_aircraft_type(public_id);
CREATE INDEX idx_product_airline_aircraft_type_company ON product_airline_aircraft_type(airline_company_id);

COMMENT ON TABLE product_airline_aircraft_type IS 'Type d''appareil de la flotte d''une compagnie -- contenu commercial (ce qui sert à vendre un billet en production ponctuelle), distinct de ref_airline_company (descriptif). Le plan de sièges est porté par product_seat_map (composant partagé avec Bus).';

CREATE TRIGGER trg_product_airline_aircraft_type_updated_at BEFORE UPDATE ON product_airline_aircraft_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_airline_aircraft_type_translation (
    aircraft_type_id  BIGINT NOT NULL REFERENCES product_airline_aircraft_type(id),
    language_code       VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    description           TEXT,
    PRIMARY KEY (aircraft_type_id, language_code)
);


-- ============================================================
-- PARTIE BUS -- session du 19/07/2026
-- Un seul référentiel de flotte, utilisé aussi bien pour le trajet de
-- ligne (booking_service_type 'bus') que pour le ramassage groupé --
-- l'objet vendu (le bus) est le même, seul le contexte de réservation
-- change (décision explicite, pas de duplication de table).
-- Équipements : même réutilisation de product_vehicle_equipment que
-- pour Transfert.
-- ============================================================
CREATE TABLE product_bus_model (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id    UUID NOT NULL DEFAULT gen_random_uuid(),
    name         VARCHAR(150) NOT NULL, -- nom propre (ex: "Mercedes Tourismo 50 places"), pas de traduction
    capacity     SMALLINT NOT NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_product_bus_model_capacity CHECK (capacity > 0)
);

CREATE UNIQUE INDEX uq_product_bus_model_public_id ON product_bus_model(public_id);

COMMENT ON TABLE product_bus_model IS 'Modèle de bus vendable -- utilisé aussi bien pour un trajet de ligne (siège numéroté) que pour un ramassage groupé (points de ramassage, gérés en Booking). Même table pour les deux usages : l''objet vendu ne change pas.';

CREATE TRIGGER trg_product_bus_model_updated_at BEFORE UPDATE ON product_bus_model
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_bus_model_translation (
    model_id       BIGINT NOT NULL REFERENCES product_bus_model(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    description    TEXT,
    PRIMARY KEY (model_id, language_code)
);

CREATE TABLE product_bus_model_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    model_id       BIGINT NOT NULL REFERENCES product_bus_model(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_bus_model_photo_model ON product_bus_model_photo(model_id);

CREATE TABLE product_bus_model_equipment (
    model_id      BIGINT NOT NULL REFERENCES product_bus_model(id),
    equipment_id  BIGINT NOT NULL REFERENCES product_vehicle_equipment(id),
    PRIMARY KEY (model_id, equipment_id)
);

COMMENT ON TABLE product_bus_model_equipment IS 'Équipements de ce bus (clim, wifi, prises...) -- réutilisation de product_vehicle_equipment, même logique que Transfert.';


-- ============================================================
-- PLAN DE SIÈGES -- composant PARTAGÉ Aérien/Bus, session du 19/07/2026
-- Reconstruit et amélioré depuis le générateur legacy (capture
-- utilisateur : colonnes gauche/colonnes droite/nb lignes -> grille,
-- siège conducteur exclu manuellement). Génération grille->sièges =
-- logique Domain PHP (ADR-002), jamais une fonction stockée -- la base
-- ne porte que le résultat final, éditable siège par siège ensuite.
-- Un plan appartient à EXACTEMENT un type d'appareil OU un modèle de
-- bus (CHECK d'exclusivité), jamais les deux -- évite une table pivot
-- polymorphe générique (cohérent avec le rejet EAV déjà acté partout).
-- Portée V1 : plan-TEMPLATE uniquement. L'attribution d'un siège précis
-- à un passager pour une résa précise est un futur ajout Booking
-- (booking_flight_detail/booking_bus_detail avec seat_id), pas ce module.
-- ============================================================
CREATE TABLE product_seat_map (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    aircraft_type_id   BIGINT REFERENCES product_airline_aircraft_type(id),
    bus_model_id       BIGINT REFERENCES product_bus_model(id),
    columns_left       SMALLINT NOT NULL,  -- paramètre du générateur (capture legacy : "Nbr colonnes gauche")
    columns_right      SMALLINT NOT NULL,  -- "Nbr colonnes droite"
    rows_count         SMALLINT NOT NULL,  -- "Nbr lignes"
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_product_seat_map_owner_exclusive CHECK (
        (aircraft_type_id IS NOT NULL AND bus_model_id IS NULL) OR
        (aircraft_type_id IS NULL AND bus_model_id IS NOT NULL)
    ),
    CONSTRAINT chk_product_seat_map_dimensions CHECK (
        columns_left >= 0 AND columns_right >= 0 AND rows_count > 0
        AND (columns_left + columns_right) > 0
    )
);

CREATE UNIQUE INDEX uq_product_seat_map_public_id ON product_seat_map(public_id);
CREATE UNIQUE INDEX uq_product_seat_map_aircraft_type ON product_seat_map(aircraft_type_id) WHERE aircraft_type_id IS NOT NULL;
CREATE UNIQUE INDEX uq_product_seat_map_bus_model ON product_seat_map(bus_model_id) WHERE bus_model_id IS NOT NULL;

COMMENT ON TABLE product_seat_map IS 'Plan de sièges -- appartient à EXACTEMENT un type d''appareil OU un modèle de bus (CHECK + unicité partielle = 1 plan par appareil/bus). columns_left/columns_right/rows_count sont les paramètres du générateur (reconduit du legacy, capture utilisateur) ; product_seat porte le résultat matérialisé et éditable.';

CREATE TRIGGER trg_product_seat_map_updated_at BEFORE UPDATE ON product_seat_map
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
CREATE TABLE product_seat (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id       UUID NOT NULL DEFAULT gen_random_uuid(),
    seat_map_id     BIGINT NOT NULL REFERENCES product_seat_map(id),
    row_number      SMALLINT NOT NULL,
    column_code     VARCHAR(2) NOT NULL, -- 'A','B'... ou 'G'/'D' pour un couloir hors lettre, texte libre volontaire
    seat_label      VARCHAR(10) NOT NULL, -- ex: 'A1' -- matérialisé (pas recalculé à la volée), affiché tel quel
    cabin_class_id  BIGINT REFERENCES ref_cabin_class(id), -- nullable : pertinent Aérien, NULL en pratique pour Bus
    is_available    BOOLEAN NOT NULL DEFAULT true, -- false = exclu (ex: emplacement conducteur de la capture legacy)
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_seat_public_id ON product_seat(public_id);
CREATE UNIQUE INDEX uq_product_seat_map_position ON product_seat(seat_map_id, row_number, column_code);
CREATE UNIQUE INDEX uq_product_seat_map_label ON product_seat(seat_map_id, seat_label);
CREATE INDEX idx_product_seat_cabin_class ON product_seat(cabin_class_id);

COMMENT ON TABLE product_seat IS 'Siège individuel matérialisé d''un plan -- généré une fois depuis columns_left/columns_right/rows_count (logique Domain PHP), puis éditable un par un (is_available=false pour exclure un emplacement, ex: conducteur -- reconduit de la capture legacy). Portée V1 : plan-template, pas d''attribution passager (voir en-tête de section).';

CREATE TRIGGER trg_product_seat_updated_at BEFORE UPDATE ON product_seat
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- ============================================================
-- PARTIE PACKAGE -- session du 19/07/2026
-- Réouverture documentée de la décision figée le 18/07 ("packages =
-- regroupement booking_folder, pas de fiche produit avec prix propre").
-- Confirmée invalidée par cas réel (capture legacy "GREECE ISLAND
-- HOPPING", prix propre 5490, composition Vol+Transfert+Hébergement+
-- Excursions). Volontairement léger (l'essentiel du travail annoncé
-- comme relevant du futur Contracting) : PAS de prix stocké ici, comme
-- partout ailleurs dans Catalogue -- seul le prix affiché dans la
-- capture legacy est explicitement exclu (Pricing/Contracting).
-- Dépend de : ref_country, ref_tag (ref_static, figés), booking_service_type
-- (Booking, figé), product_visa (Catalogue, ce module).
-- ============================================================
CREATE TABLE product_package (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id        UUID NOT NULL DEFAULT gen_random_uuid(),
    duration_days    SMALLINT,
    duration_nights  SMALLINT,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_package_public_id ON product_package(public_id);

COMMENT ON TABLE product_package IS 'Voyage organisé/package vendable comme un produit à part entière, prix propre affiché (mais PAS stocké ici -- futur Pricing/Contracting, cohérent avec le reste du module). Réouverture documentée du 19/07 de la décision figée le 18/07 (packages = simple regroupement booking_folder), invalidée par capture legacy réelle.';

CREATE TRIGGER trg_product_package_updated_at BEFORE UPDATE ON product_package
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_package_translation (
    package_id     BIGINT NOT NULL REFERENCES product_package(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(255) NOT NULL, -- ex: "GREECE ISLAND HOPPING"
    description    TEXT,
    PRIMARY KEY (package_id, language_code)
);

CREATE TABLE product_package_photo (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    package_id     BIGINT NOT NULL REFERENCES product_package(id),
    url            VARCHAR(500) NOT NULL,
    display_order  SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_product_package_photo_package ON product_package_photo(package_id);

-- ------------------------------------------------------------
-- Pays : multi-select (package combiné multi-pays) -- remplace le
-- couple Pays/Destination de la capture legacy ; "Destination" (zone
-- large type Europe/Asie) explicitement abandonné (décision utilisateur
-- 19/07), pas remplacé par un référentiel équivalent.
-- ------------------------------------------------------------
CREATE TABLE product_package_country (
    package_id  BIGINT NOT NULL REFERENCES product_package(id),
    country_id  BIGINT NOT NULL REFERENCES ref_country(id),
    PRIMARY KEY (package_id, country_id)
);

COMMENT ON TABLE product_package_country IS 'Pays couverts par le package, multi-select (combiné multi-pays). Remplace le couple Pays/Destination(zone) de la capture legacy -- la notion de zone large (Europe/Asie...) est abandonnée (décision explicite 19/07).';

-- ------------------------------------------------------------
-- Thèmes : réutilise ref_tag (ref_static, figé) -- même référentiel
-- que celui déjà utilisé pour l'hôtel (ref_accommodation_tag), pas de
-- vocabulaire dupliqué.
-- ------------------------------------------------------------
CREATE TABLE product_package_tag (
    package_id  BIGINT NOT NULL REFERENCES product_package(id),
    tag_id      BIGINT NOT NULL REFERENCES ref_tag(id),
    PRIMARY KEY (package_id, tag_id)
);

COMMENT ON TABLE product_package_tag IS 'Thèmes du package (ex: "jeune", capture legacy) -- réutilisation de ref_tag, pas de référentiel "thème" dédié dupliqué.';

-- ------------------------------------------------------------
-- Composition : quels TYPES de service sont inclus/optionnels, dans
-- quel ordre. Reste au niveau "quel type de prestation" (réutilise
-- booking_service_type), pas "quel produit/fournisseur précis" -- ce
-- choix précis au moment de la vente relève du futur Contracting.
-- Table dédiée (pas de simple PK composite package_id+service_type_code)
-- car un même type de service peut apparaître plusieurs fois dans un
-- package (ex: "Excursion Hammamet" ET "Soirée cabaret" sont deux
-- lignes optionnelles distinctes, toutes deux service_type_code=
-- 'excursion') -- chaque ligne porte donc son propre libellé traduit.
-- ------------------------------------------------------------
CREATE TABLE product_package_component (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    package_id         BIGINT NOT NULL REFERENCES product_package(id),
    service_type_code  VARCHAR(30) NOT NULL REFERENCES booking_service_type(code),
    is_optional        BOOLEAN NOT NULL DEFAULT false,
    display_order      SMALLINT NOT NULL DEFAULT 0,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_product_package_component_public_id ON product_package_component(public_id);
CREATE INDEX idx_product_package_component_package ON product_package_component(package_id, display_order);
CREATE INDEX idx_product_package_component_service_type ON product_package_component(service_type_code);

COMMENT ON TABLE product_package_component IS 'Ligne de composition d''un package -- type de service (réutilise booking_service_type) + optionnel/inclus + ordre d''affichage. Le libellé précis de la ligne ("Excursion Hammamet", "Soirée cabaret") vit dans product_package_component_translation, car deux lignes peuvent partager le même service_type_code.';

CREATE TRIGGER trg_product_package_component_updated_at BEFORE UPDATE ON product_package_component
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE product_package_component_translation (
    component_id   BIGINT NOT NULL REFERENCES product_package_component(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(255) NOT NULL,
    description    TEXT,
    PRIMARY KEY (component_id, language_code)
);

-- ------------------------------------------------------------
-- Visa requis pour ce package -- réutilisation directe des fiches
-- product_visa déjà structurées (pays/type d'entrée/documents requis),
-- PAS un référentiel de noms en texte libre comme la capture legacy
-- ("Visa Thailand", "Visa Dubai"...). Relation commerciale structurante
-- (l'agent peut vendre le visa associé), pas un simple contenu CMS.
-- ------------------------------------------------------------
CREATE TABLE product_package_visa (
    package_id  BIGINT NOT NULL REFERENCES product_package(id),
    visa_id     BIGINT NOT NULL REFERENCES product_visa(id),
    PRIMARY KEY (package_id, visa_id)
);

COMMENT ON TABLE product_package_visa IS 'Visa(s) requis pour ce package -- réutilise product_visa (fiches déjà structurées), remplace la checklist en texte libre du legacy.';
