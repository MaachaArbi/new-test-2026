-- ============================================================================
-- Module      : Référentiel Hébergement & Géographie (ref_)
-- Objet       : Miroir local des données OctaSoft Static Data (pull
--               uniquement, produit séparé, hors périmètre de conception
--               ici) + ajouts locaux propres au client, selon l'entité.
-- Version     : 1.0 - figé le 17 juillet 2026
-- Ordre       : 8ème script à exécuter (après ref_common, party, core,
--               booking, reglements, cash-management, pointvente) —
--               dépend uniquement de schema-ref-common.sql (ref_language).
-- Dépend de   : schema-ref-common.sql (ref_language, étendu — voir
--               ref-common-static-extension.diff)
-- ============================================================================
--
-- PRINCIPE FONDATEUR — convention transverse à toutes les entités du module :
--
--   - id BIGINT identity + public_id UUID (ADR-018), comme partout ailleurs
--     dans le projet.
--
--   - oct_code : code de réconciliation universel fourni par OctaSoft Static
--     Data, identique chez tous les clients OctaSoft pour désigner la même
--     entité réelle. Remplace le mapping manuel id_rapprochement du legacy.
--
--       -> NOT NULL  quand l'ajout local n'est PAS permis pour cette entité
--          (entité purement importée : content_provider, ref_country,
--          ref_region, ref_city, ref_language/ref_currency [extension],
--          ref_board_type, ref_property_category, ref_property_rating).
--
--       -> NULLABLE + UNIQUE (index partiel WHERE oct_code IS NOT NULL)
--          quand l'ajout local est permis pour cette entité. Une ligne
--          locale a oct_code = NULL EN PERMANENCE : l'utilisateur ne peut
--          jamais le saisir manuellement (ref_accommodation, ref_amenity*,
--          ref_hotel_chain, ref_tag*, ref_accommodation_location_type,
--          ref_room_category, ref_option, ref_supplement).
--
--   - Signal "ajout local" = oct_code IS NULL. Pas de colonne dédiée
--     (booléen jugé redondant, décision actée 17/07).
--
--   - Synchronisation en PULL uniquement (pas de webhook/push côté client).
--     OctaSoft Static Data est un produit séparé, avec son propre chat/
--     Project dédié — ce module ne conçoit QUE le côté client (tables
--     miroir locales), jamais le moteur de rapprochement fournisseur.
--
--   - Conflit oct_code (un ajout local dupliquant une entité qui apparaît
--     plus tard côté central, avec un vrai oct_code) : accepté tel quel,
--     AUCUNE structure de rapprochement en V1 (décision actée 17/07 —
--     l'utilisateur ne peut de toute façon jamais saisir oct_code
--     manuellement, donc pas de risque de collision, juste un doublon
--     visuel possible, résolu manuellement si besoin).
--
--   - Ordre d'import : un import ne peut créer/mettre à jour une entité
--     dont une FK vers une autre entité de ce module ne résout pas un
--     oct_code déjà présent localement — la ligne est REJETÉE (jamais
--     acceptée avec une FK NULL temporaire), à retraiter au batch suivant
--     une fois le parent synchronisé (décision actée 17/07, testée sur
--     ref_region/ref_city).
--
--   - Mapping vocabulaire fournisseur -> référentiel unifié (ex: Webbeds
--     "Half Board" -> ref_board_type "Demi-pension") : JAMAIS modélisé
--     côté client. C'est le travail du moteur de rapprochement OctaSoft
--     Static Data, en amont, hors périmètre. Les données reçues plus tard
--     par le futur module Provider Integration arriveront déjà taguées
--     avec le bon oct_code — jointure directe, jamais de table de mapping
--     intermédiaire ici (décision actée 17/07, point 6 du cadrage initial).
--
--   - EAV / table technique générique rejetée par principe (cohérent avec
--     la décision déjà actée sur Booking) : chaque domaine a sa propre
--     table, avec la même convention de colonnes reconduite partout,
--     jamais une table pivot unique par-dessus.
--
-- ============================================================================


-- ============================================================================
-- SECTION 1 — GÉOGRAPHIE
-- Hiérarchie stricte confirmée (17/07) : Pays -> Région -> Ville. Aucune
-- ville orpheline directement au pays. Aucun ajout local possible sur les
-- 3 entités (oct_code NOT NULL) : ce sont des référentiels fermés.
-- Le pays/la région ne sont jamais dupliqués en aval (pas de country_id ni
-- de country_oct_code sur ref_region/ref_city) : atteignables par
-- transitivité via la FK, seule source de vérité — éviter toute dérive
-- entre deux copies du même oct_code.
-- ============================================================================

-- ------------------------------------------------------------
-- ref_country : référentiel pays. Nom porté uniquement dans
-- ref_country_translation (pas de colonne name ici, cf. pattern
-- party_role/party_role_translation).
-- ------------------------------------------------------------
CREATE TABLE ref_country (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id    UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code     VARCHAR(50) NOT NULL,
    alpha2       CHAR(2) NOT NULL,   -- ISO 3166-1 alpha-2
    alpha3       CHAR(3) NOT NULL,   -- ISO 3166-1 alpha-3
    numeric_code CHAR(3) NOT NULL,   -- ISO 3166-1 numérique
    dial_code    VARCHAR(10),        -- ex: '+216', tel que fourni par OctaSoft Static Data
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_country_public_id ON ref_country(public_id);
CREATE UNIQUE INDEX uq_ref_country_oct_code ON ref_country(oct_code);
CREATE UNIQUE INDEX uq_ref_country_alpha2 ON ref_country(alpha2);
CREATE UNIQUE INDEX uq_ref_country_alpha3 ON ref_country(alpha3);
CREATE UNIQUE INDEX uq_ref_country_numeric ON ref_country(numeric_code);

COMMENT ON TABLE ref_country IS 'Référentiel pays, miroir local complet OctaSoft Static Data (pull). Aucun ajout local possible : oct_code NOT NULL. Nom traduit dans ref_country_translation.';

CREATE TRIGGER trg_ref_country_updated_at BEFORE UPDATE ON ref_country
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_country_translation (
    country_id     BIGINT NOT NULL REFERENCES ref_country(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (country_id, language_code)
);

COMMENT ON TABLE ref_country_translation IS 'Nom du pays par langue (EN pivot inclus), fourni par OctaSoft Static Data.';

-- ------------------------------------------------------------
-- ref_region : zone touristique (ex: Hammamet, Djerba), rattachée
-- directement au pays.
-- ------------------------------------------------------------
CREATE TABLE ref_region (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50) NOT NULL,
    country_id BIGINT NOT NULL REFERENCES ref_country(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_region_public_id ON ref_region(public_id);
CREATE UNIQUE INDEX uq_ref_region_oct_code ON ref_region(oct_code);
CREATE INDEX idx_ref_region_country ON ref_region(country_id);

COMMENT ON TABLE ref_region IS 'Zone touristique (ex: Hammamet, Djerba), rattachée directement au pays. Aucun ajout local possible : oct_code NOT NULL. Nom traduit dans ref_region_translation.';

CREATE TRIGGER trg_ref_region_updated_at BEFORE UPDATE ON ref_region
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_region_translation (
    region_id      BIGINT NOT NULL REFERENCES ref_region(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (region_id, language_code)
);

COMMENT ON TABLE ref_region_translation IS 'Nom de la région/zone touristique par langue, fourni par OctaSoft Static Data.';

-- ------------------------------------------------------------
-- ref_city : ville, rattachée obligatoirement à une région (hiérarchie
-- stricte, confirmée 17/07 : pas de ville orpheline directement au pays).
-- ------------------------------------------------------------
CREATE TABLE ref_city (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50) NOT NULL,
    region_id  BIGINT NOT NULL REFERENCES ref_region(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_city_public_id ON ref_city(public_id);
CREATE UNIQUE INDEX uq_ref_city_oct_code ON ref_city(oct_code);
CREATE INDEX idx_ref_city_region ON ref_city(region_id);

COMMENT ON TABLE ref_city IS 'Ville, rattachée obligatoirement à une région (hiérarchie stricte Pays->Région->Ville, confirmée 17/07). Aucun ajout local possible : oct_code NOT NULL. Nom traduit dans ref_city_translation. Le pays est atteignable par transitivité via region_id, jamais dupliqué ici.';

CREATE TRIGGER trg_ref_city_updated_at BEFORE UPDATE ON ref_city
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_city_translation (
    city_id        BIGINT NOT NULL REFERENCES ref_city(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (city_id, language_code)
);

COMMENT ON TABLE ref_city_translation IS 'Nom de la ville par langue, fourni par OctaSoft Static Data.';


-- ============================================================================
-- SECTION 2 — FOURNISSEUR DE CONTENU
-- content_provider est une entité FONDATRICE créée en avance de besoin du
-- futur module Provider Integration (module 4, cf. 00-INDEX.md). Portée
-- volontairement minimale (juste ce qui sert le mapping langues aujourd'hui).
-- Distincte de booking_payment.provider_reference (passerelle de paiement,
-- catégorie différente, cycle de vie différent — tranché 17/07).
--
-- >>> LE FUTUR MODULE PROVIDER INTEGRATION DEVRA RÉUTILISER CETTE TABLE PAR
-- >>> EXTENSION (table compagnon 1-1, même pattern que
-- >>> cash_payment_method_routing sur reglement_payment_method), JAMAIS LA
-- >>> RECRÉER NI LA DUPLIQUER SOUS UN AUTRE NOM.
-- ============================================================================

CREATE TABLE content_provider (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50) NOT NULL,
    name       VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_content_provider_public_id ON content_provider(public_id);
CREATE UNIQUE INDEX uq_content_provider_oct_code ON content_provider(oct_code);

COMMENT ON TABLE content_provider IS 'Fournisseur de contenu référentiel (Hotelbeds/Webbeds/GNV...), quel que soit le protocole technique (XML/JSON/GraphQL). Entité FONDATRICE pour le futur module Provider Integration (module 4) : à étendre par table compagnon, jamais recréer ni dupliquer. Aucun ajout local possible : oct_code NOT NULL.';

CREATE TRIGGER trg_content_provider_updated_at BEFORE UPDATE ON content_provider
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE content_provider_language (
    content_provider_id  BIGINT NOT NULL REFERENCES content_provider(id),
    language_code         VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    PRIMARY KEY (content_provider_id, language_code)
);

COMMENT ON TABLE content_provider_language IS 'Langues dans lesquelles un fournisseur de contenu accepte d''être interrogé. Présence de la ligne = support de la langue, aucune colonne de priorité (décision 17/07).';


-- ============================================================================
-- SECTION 3 — VOCABULAIRE HÉBERGEMENT (référentiels fermés, sans ajout local)
-- ============================================================================

-- ------------------------------------------------------------
-- ref_board_type : type de pension/arrangement (LS/LPD/DP/PC/All
-- Inclusive...). Référentiel unique et fermé, PAS par fournisseur
-- (décision 17/07, point 6 du cadrage initial).
-- ------------------------------------------------------------
CREATE TABLE ref_board_type (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_board_type_public_id ON ref_board_type(public_id);
CREATE UNIQUE INDEX uq_ref_board_type_oct_code ON ref_board_type(oct_code);

COMMENT ON TABLE ref_board_type IS 'Type de pension/arrangement (LS/LPD/DP/PC/All Inclusive...), référentiel unique et fermé. Aucun ajout local possible : oct_code NOT NULL. Nom traduit dans ref_board_type_translation. Aucun mapping fournisseur-spécifique modélisé ici : résolu en amont par OctaSoft Static Data (hors périmètre) ; le futur Provider Integration recevra directement des données taguées avec le bon oct_code.';

CREATE TRIGGER trg_ref_board_type_updated_at BEFORE UPDATE ON ref_board_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_board_type_translation (
    board_type_id  BIGINT NOT NULL REFERENCES ref_board_type(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (board_type_id, language_code)
);

COMMENT ON TABLE ref_board_type_translation IS 'Nom du type de pension par langue, fourni par OctaSoft Static Data.';

-- ------------------------------------------------------------
-- ref_accommodation_rental_mode : distinction structurelle PROPRE à MyGo
-- (room vs whole_unit), PAS fournie par OctaSoft. Portée par la catégorie
-- (ref_property_category.rental_mode_code), pas par l'hébergement lui-même
-- (revirement acté 17/07). Référentiel fixe interne : pas de oct_code,
-- pas d'ajout local, 2 lignes.
-- ------------------------------------------------------------
CREATE TABLE ref_accommodation_rental_mode (
    code        VARCHAR(20) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE ref_accommodation_rental_mode IS 'Distinction structurelle interne MyGo (room = vente par chambre/unité de stock typée ; whole_unit = vente de l''hébergement entier). Non fourni par OctaSoft. Référentiel fixe, pas de oct_code, pas d''ajout local.';

INSERT INTO ref_accommodation_rental_mode (code, sort_order) VALUES
    ('room',       0),
    ('whole_unit', 1);

-- ------------------------------------------------------------
-- ref_property_category : TYPE d'hébergement (Hôtel/Villa/Appartement/
-- Maison d'hôte...). Revirement explicite acté en session (17/07) :
-- décision initiale du cadrage (17/07 matin) prévoyait une simple colonne
-- `type` sur l'hébergement — révisée en cours de session pour un vrai
-- référentiel, cohérent avec le pattern oct_code+translation du reste du
-- module (une colonne simple n'aurait pas permis la synchronisation ni la
-- traduction proprement).
-- Porte rental_mode_code : chaque catégorie détermine de façon
-- déterministe son mode de location (ex: Hotel->room, Villa/Appartement/
-- Maison d'hôte->whole_unit) — confirmé 17/07.
-- ------------------------------------------------------------
CREATE TABLE ref_property_category (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code           VARCHAR(50) NOT NULL,
    rental_mode_code   VARCHAR(20) NOT NULL REFERENCES ref_accommodation_rental_mode(code),
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_property_category_public_id ON ref_property_category(public_id);
CREATE UNIQUE INDEX uq_ref_property_category_oct_code ON ref_property_category(oct_code);

COMMENT ON TABLE ref_property_category IS 'TYPE d''hébergement (Hôtel/Villa/Appartement/Maison d''hôte...). Revirement 17/07 : référentiel à part entière, pas une simple colonne (décision initiale du cadrage révisée). Porte rental_mode_code (déterministe par catégorie). Aucun ajout local possible : oct_code NOT NULL. Nom traduit dans ref_property_category_translation.';

CREATE TRIGGER trg_ref_property_category_updated_at BEFORE UPDATE ON ref_property_category
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_property_category_translation (
    property_category_id  BIGINT NOT NULL REFERENCES ref_property_category(id),
    language_code          VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                    VARCHAR(150) NOT NULL,
    PRIMARY KEY (property_category_id, language_code)
);

COMMENT ON TABLE ref_property_category_translation IS 'Nom de la catégorie d''hébergement par langue, fourni par OctaSoft Static Data.';

-- ------------------------------------------------------------
-- ref_property_rating : CLASSEMENT en étoiles (1 à 5 Stars). Référentiel
-- indépendant de ref_property_category (type vs classement, confirmé
-- 17/07 : aucun lien structurel entre les deux).
-- stars_number : raccourci d'affichage numérique uniquement (évite de
-- parser le nom traduit), NULLABLE (classements non numériques possibles,
-- ex: "Boutique").
-- ------------------------------------------------------------
CREATE TABLE ref_property_rating (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id    UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code     VARCHAR(50) NOT NULL,
    stars_number SMALLINT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_property_rating_public_id ON ref_property_rating(public_id);
CREATE UNIQUE INDEX uq_ref_property_rating_oct_code ON ref_property_rating(oct_code);

COMMENT ON TABLE ref_property_rating IS 'CLASSEMENT en étoiles (1 à 5 Stars). Indépendant de ref_property_category. stars_number = raccourci d''affichage numérique, nullable. Aucun ajout local possible : oct_code NOT NULL. Nom traduit dans ref_property_rating_translation.';

CREATE TRIGGER trg_ref_property_rating_updated_at BEFORE UPDATE ON ref_property_rating
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_property_rating_translation (
    property_rating_id  BIGINT NOT NULL REFERENCES ref_property_rating(id),
    language_code         VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                   VARCHAR(150) NOT NULL,
    PRIMARY KEY (property_rating_id, language_code)
);

COMMENT ON TABLE ref_property_rating_translation IS 'Nom du classement par langue, fourni par OctaSoft Static Data.';


-- ============================================================================
-- SECTION 4 — HÉBERGEMENT (entité centrale)
-- ============================================================================

-- ------------------------------------------------------------
-- ref_accommodation : hébergement (hôtel/villa/appartement/maison
-- d'hôte...). PREMIÈRE entité "principale" du module avec ajout local
-- permis (oct_code NULLABLE — cas d'usage cité dès le cadrage initial :
-- hébergements de test ajoutés par le client).
-- Nom = nom propre, PAS de table translation (tranché 17/07 : un nom
-- d'hôtel ne se traduit pas, contrairement à chambres/arrangements/
-- suppléments/descriptions qui ont un vrai besoin de traduction).
-- city_id = seul point d'entrée géographique (région/pays atteignables
-- par transitivité, jamais dupliqués).
-- category_id porte aussi le rental_mode par transitivité
-- (ref_property_category.rental_mode_code).
-- rating_id NULLABLE (un ajout local ou un hébergement non classé n'a pas
-- forcément de rating).
-- Capacité descriptive, description, photos, tags/amenities/options/
-- chaîne/suppléments rattachés : REPORTÉS (voir sujets-reportes.md #32).
-- ------------------------------------------------------------
CREATE TABLE ref_accommodation (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id    UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code     VARCHAR(50),
    name         VARCHAR(255) NOT NULL,
    city_id      BIGINT NOT NULL REFERENCES ref_city(id),
    category_id  BIGINT NOT NULL REFERENCES ref_property_category(id),
    rating_id    BIGINT REFERENCES ref_property_rating(id),
    latitude     NUMERIC(9,6),
    longitude    NUMERIC(9,6),
    address      TEXT,
    phone        VARCHAR(50),
    email        VARCHAR(255),
    website      VARCHAR(255),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_accommodation_public_id ON ref_accommodation(public_id);
CREATE UNIQUE INDEX uq_ref_accommodation_oct_code ON ref_accommodation(oct_code) WHERE oct_code IS NOT NULL;
CREATE INDEX idx_ref_accommodation_city ON ref_accommodation(city_id);
CREATE INDEX idx_ref_accommodation_category ON ref_accommodation(category_id);

COMMENT ON TABLE ref_accommodation IS 'Hébergement (hôtel/villa/appartement/maison d''hôte...). Ajout local permis : oct_code NULLABLE. Nom propre, pas de traduction. city_id seul point d''entrée géographique. category_id porte le rental_mode par transitivité. rating_id nullable. Capacité/description/photos/liaisons tags-amenities-options-chaîne-suppléments : reportés, voir sujets-reportes.md #32.';

CREATE TRIGGER trg_ref_accommodation_updated_at BEFORE UPDATE ON ref_accommodation
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- ============================================================================
-- SECTION 5 — VOCABULAIRE HÉBERGEMENT (référentiels avec ajout local permis)
-- ============================================================================

-- ------------------------------------------------------------
-- ref_amenity_type / ref_amenity : type d'aménagement + aménagement
-- (équipement physique avec icône, ex: Animaux autorisés, Salon de
-- coiffure, Équipements pour handicapés). photo_url = URL vers fichier
-- (hébergement futur Minio/S3, non tranché au 17/07).
-- Distinct de ref_option (souhait gratuit sans icône, voir plus bas).
-- ------------------------------------------------------------
CREATE TABLE ref_amenity_type (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50),
    photo_url  VARCHAR(500),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_amenity_type_public_id ON ref_amenity_type(public_id);
CREATE UNIQUE INDEX uq_ref_amenity_type_oct_code ON ref_amenity_type(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_amenity_type IS 'Type d''aménagement (regroupement). Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_amenity_type_translation.';

CREATE TRIGGER trg_ref_amenity_type_updated_at BEFORE UPDATE ON ref_amenity_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_amenity_type_translation (
    amenity_type_id  BIGINT NOT NULL REFERENCES ref_amenity_type(id),
    language_code     VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name               VARCHAR(150) NOT NULL,
    PRIMARY KEY (amenity_type_id, language_code)
);

CREATE TABLE ref_amenity (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id        UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code         VARCHAR(50),
    amenity_type_id  BIGINT NOT NULL REFERENCES ref_amenity_type(id),
    photo_url        VARCHAR(500),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_amenity_public_id ON ref_amenity(public_id);
CREATE UNIQUE INDEX uq_ref_amenity_oct_code ON ref_amenity(oct_code) WHERE oct_code IS NOT NULL;
CREATE INDEX idx_ref_amenity_type ON ref_amenity(amenity_type_id);

COMMENT ON TABLE ref_amenity IS 'Aménagement (équipement physique avec icône, ex: Animaux autorisés, Salon de coiffure), rattaché à ref_amenity_type. Distinct de ref_option (souhait gratuit sans icône). Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_amenity_translation. Liaison vers ref_accommodation : reportée (sujets-reportes.md #32).';

CREATE TRIGGER trg_ref_amenity_updated_at BEFORE UPDATE ON ref_amenity
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_amenity_translation (
    amenity_id     BIGINT NOT NULL REFERENCES ref_amenity(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (amenity_id, language_code)
);

-- ------------------------------------------------------------
-- ref_hotel_chain : chaîne hôtelière. Nom propre (pas de traduction,
-- même principe que ref_accommodation.name).
-- ------------------------------------------------------------
CREATE TABLE ref_hotel_chain (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50),
    name       VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_hotel_chain_public_id ON ref_hotel_chain(public_id);
CREATE UNIQUE INDEX uq_ref_hotel_chain_oct_code ON ref_hotel_chain(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_hotel_chain IS 'Chaîne hôtelière, nom propre non traduit. Ajout local permis : oct_code NULLABLE. Liaison vers ref_accommodation (chain_id) : reportée (sujets-reportes.md #32).';

CREATE TRIGGER trg_ref_hotel_chain_updated_at BEFORE UPDATE ON ref_hotel_chain
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
-- ref_tag_category / ref_tag : tags de vente (hiérarchie confirmée
-- 17/07, même principe que ref_amenity/ref_amenity_type).
-- ------------------------------------------------------------
CREATE TABLE ref_tag_category (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_tag_category_public_id ON ref_tag_category(public_id);
CREATE UNIQUE INDEX uq_ref_tag_category_oct_code ON ref_tag_category(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_tag_category IS 'Catégorie de tag (regroupement). Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_tag_category_translation.';

CREATE TRIGGER trg_ref_tag_category_updated_at BEFORE UPDATE ON ref_tag_category
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_tag_category_translation (
    tag_category_id  BIGINT NOT NULL REFERENCES ref_tag_category(id),
    language_code     VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name               VARCHAR(150) NOT NULL,
    PRIMARY KEY (tag_category_id, language_code)
);

CREATE TABLE ref_tag (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id       UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code        VARCHAR(50),
    tag_category_id BIGINT NOT NULL REFERENCES ref_tag_category(id),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_tag_public_id ON ref_tag(public_id);
CREATE UNIQUE INDEX uq_ref_tag_oct_code ON ref_tag(oct_code) WHERE oct_code IS NOT NULL;
CREATE INDEX idx_ref_tag_category ON ref_tag(tag_category_id);

COMMENT ON TABLE ref_tag IS 'Tag de vente, rattaché à ref_tag_category. Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_tag_translation. Liaison vers ref_accommodation : reportée (sujets-reportes.md #32).';

CREATE TRIGGER trg_ref_tag_updated_at BEFORE UPDATE ON ref_tag
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_tag_translation (
    tag_id         BIGINT NOT NULL REFERENCES ref_tag(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (tag_id, language_code)
);

-- ------------------------------------------------------------
-- ref_accommodation_location_type : type d'implantation (Centre-ville/
-- Bord de mer/Palmeraie...). Préfixé accommodation_ pour ne pas le
-- confondre avec la géographie réelle (ref_city/ref_region). Peu utilisé
-- en pratique (signalé par l'utilisateur, 17/07), gardé quand même.
-- ------------------------------------------------------------
CREATE TABLE ref_accommodation_location_type (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_accloctype_public_id ON ref_accommodation_location_type(public_id);
CREATE UNIQUE INDEX uq_ref_accloctype_oct_code ON ref_accommodation_location_type(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_accommodation_location_type IS 'Type d''implantation de l''hébergement (Centre-ville/Bord de mer/Palmeraie...). Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_accommodation_location_type_translation. Peu utilisé en pratique (signalé 17/07).';

CREATE TRIGGER trg_ref_accloctype_updated_at BEFORE UPDATE ON ref_accommodation_location_type
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_accommodation_location_type_translation (
    location_type_id  BIGINT NOT NULL REFERENCES ref_accommodation_location_type(id),
    language_code       VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                 VARCHAR(150) NOT NULL,
    PRIMARY KEY (location_type_id, language_code)
);

-- ------------------------------------------------------------
-- ref_room_category : catégorie de chambre, référentiel GLOBAL PARTAGÉ
-- (résolution du point ouvert en tout début de session, confirmée
-- 17/07 : pas de table fille par hébergement — "Chambre Double"/"Suite
-- Junior" réutilisables par tous les hôtels). Volontairement minimal
-- (pas de description/photo, confirmé).
-- ------------------------------------------------------------
CREATE TABLE ref_room_category (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_room_category_public_id ON ref_room_category(public_id);
CREATE UNIQUE INDEX uq_ref_room_category_oct_code ON ref_room_category(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_room_category IS 'Catégorie de chambre, référentiel global partagé (pas de table fille par hébergement -- résolution du point ouvert en cadrage initial, confirmée 17/07). Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_room_category_translation. Volontairement minimal (pas de description/photo).';

CREATE TRIGGER trg_ref_room_category_updated_at BEFORE UPDATE ON ref_room_category
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_room_category_translation (
    room_category_id  BIGINT NOT NULL REFERENCES ref_room_category(id),
    language_code       VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                 VARCHAR(150) NOT NULL,
    PRIMARY KEY (room_category_id, language_code)
);

-- ------------------------------------------------------------
-- ref_option : souhait/demande GRATUITE, sujette à disponibilité,
-- n'engageant ni l'agence ni l'hébergement (ex: Arrivée tardive si
-- possible, Chambres communicantes si possible, Traitement VIP, Chambre
-- non-fumeur si possible, Séjour de noce). Distincte de ref_amenity
-- (équipement physique avec icône) -- confirmé 17/07. Table plate, pas
-- de catégorie.
-- ------------------------------------------------------------
CREATE TABLE ref_option (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_option_public_id ON ref_option(public_id);
CREATE UNIQUE INDEX uq_ref_option_oct_code ON ref_option(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_option IS 'Souhait/demande gratuite, sujette à disponibilité, sans engagement (ex: Arrivée tardive si possible, Traitement VIP). Distincte de ref_amenity (équipement physique avec icône). Table plate, pas de catégorie. Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_option_translation. Liaison vers ref_accommodation/réservation : reportée (sujets-reportes.md #32).';

CREATE TRIGGER trg_ref_option_updated_at BEFORE UPDATE ON ref_option
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_option_translation (
    option_id      BIGINT NOT NULL REFERENCES ref_option(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (option_id, language_code)
);


-- ============================================================================
-- SECTION 6 — SUPPLÉMENTS & RÉDUCTIONS
-- ============================================================================

-- ------------------------------------------------------------
-- ref_charge_unit / ref_charge_frequency : les 2 dimensions du "Payment
-- mode" pour ref_supplement (per_person/per_room x one_time/per_night).
-- Référentiels fixes internes, façon ref_accommodation_rental_mode : pas
-- de oct_code, pas d'ajout local, tables volontairement petites (2 lignes
-- chacune, max ~30 attendu -- confirmé non critique par l'utilisateur).
-- ------------------------------------------------------------
CREATE TABLE ref_charge_unit (
    code        VARCHAR(20) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0
);

INSERT INTO ref_charge_unit (code, sort_order) VALUES
    ('per_person', 0),
    ('per_room',   1);

CREATE TABLE ref_charge_frequency (
    code        VARCHAR(20) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0
);

INSERT INTO ref_charge_frequency (code, sort_order) VALUES
    ('one_time',  0),
    ('per_night', 1);

-- ------------------------------------------------------------
-- ref_supplement : définition d'un supplément/réduction applicable à un
-- hébergement (vocabulaire + règle de plage récurrente + mode de
-- facturation). Le MONTANT lui-même n'est PAS ici -- futur module
-- Pricing (hors périmètre, cf. sujets-reportes.md).
--
-- from_mmdd/to_mmdd : plage récurrente ANNUELLE (ex: supplément fêtes de
-- fin d'année du 20/12 au 05/01, applicable toutes les années), format
-- compact MOIS*100+JOUR (ex: 20/12 -> 1220, 05/01 -> 105) choisi pour la
-- performance en lecture (comparaison entière directe, indexable, pas de
-- calcul à la volée) -- un vrai DATE porterait une année arbitraire et
-- fausse, et compliquerait la logique de plage à cheval sur le nouvel an
-- sans aucun bénéfice.
-- Logique de lecture applicative :
--   - si from_mmdd <= to_mmdd : MMDD BETWEEN from_mmdd AND to_mmdd
--   - si from_mmdd >  to_mmdd (plage à cheval sur le nouvel an) :
--     MMDD >= from_mmdd OR MMDD <= to_mmdd
-- NULLABLE (les deux ensemble) : un supplément peut être permanent (pas
-- de plage) -- CHECK chk_ref_supplement_mmdd_range impose la cohérence
-- (les deux NULL ou les deux renseignés, jamais un seul).
-- ------------------------------------------------------------
CREATE TABLE ref_supplement (
    id                     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id              UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code               VARCHAR(50),
    from_mmdd              SMALLINT,
    to_mmdd                SMALLINT,
    is_required            BOOLEAN NOT NULL DEFAULT false,
    charge_unit_code       VARCHAR(20) NOT NULL REFERENCES ref_charge_unit(code),
    charge_frequency_code  VARCHAR(20) NOT NULL REFERENCES ref_charge_frequency(code),
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_ref_supplement_mmdd_range CHECK (
        (from_mmdd IS NULL AND to_mmdd IS NULL) OR (from_mmdd IS NOT NULL AND to_mmdd IS NOT NULL)
    ),
    CONSTRAINT chk_ref_supplement_mmdd_valid CHECK (
        (from_mmdd IS NULL OR (from_mmdd BETWEEN 101 AND 1231))
        AND (to_mmdd IS NULL OR (to_mmdd BETWEEN 101 AND 1231))
    )
);

CREATE UNIQUE INDEX uq_ref_supplement_public_id ON ref_supplement(public_id);
CREATE UNIQUE INDEX uq_ref_supplement_oct_code ON ref_supplement(oct_code) WHERE oct_code IS NOT NULL;

COMMENT ON TABLE ref_supplement IS 'Définition d''un supplément/réduction (vocabulaire + plage récurrente annuelle + mode de facturation). Montant : hors périmètre, futur module Pricing. from_mmdd/to_mmdd : format MMDD compact (mois*100+jour), NULLABLE si permanent -- voir logique de lecture en commentaire de section. Ajout local permis : oct_code NULLABLE. Nom traduit dans ref_supplement_translation.';

CREATE TRIGGER trg_ref_supplement_updated_at BEFORE UPDATE ON ref_supplement
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_supplement_translation (
    supplement_id  BIGINT NOT NULL REFERENCES ref_supplement(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name           VARCHAR(150) NOT NULL,
    PRIMARY KEY (supplement_id, language_code)
);

-- ============================================================================
-- FIN schema-ref-static-v1.sql
-- Points reportés (description/photos/capacité/liaisons hébergement,
-- entités pas encore dans OctaSoft, organisation transverse des
-- référentiels) : voir sujets-reportes.md #32-#35.
-- ============================================================================
-- ============================================================
-- Réouverture ponctuelle documentée (18/07/2026), justifiée par la
-- session Product/Catalogue : fermeture du point ouvert #112 laissé
-- par ref_static V1.0 ("toutes les liaisons ref_accommodation <->
-- vocabulaire"). Additif pur, ref_accommodation elle-même non touchée.
--
-- Périmètre retenu (décision explicite en session) : on relie
-- UNIQUEMENT les référentiels déjà existants dans ref_static --
-- amenity, option, location_type, tag (= "Services" dans le
-- vocabulaire métier de l'utilisateur). "Thèmes" (ost_sht_hotels_themes
-- en legacy) n'a PAS de référentiel ref_static et n'est volontairement
-- PAS créé : décision explicite de ne pas ajouter de nouvelle entité,
-- cohérente avec le principe acté par ailleurs qu'un "thème" (Famille,
-- Romantique...) est un classement marketing web, migré vers le futur
-- CMS, hors périmètre MyGo -- pas un champ métier ou un critère de
-- recherche technique. Abandon documenté, pas un oubli.
-- ============================================================

CREATE TABLE ref_accommodation_amenity (
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    amenity_id        BIGINT NOT NULL REFERENCES ref_amenity(id),
    PRIMARY KEY (accommodation_id, amenity_id)
);

COMMENT ON TABLE ref_accommodation_amenity IS 'Aménagements physiques de l''hôtel entier (WiFi, piscine, salon de coiffure...). Pur lien descriptif booléen -- ferme le point ouvert #112. Distinct de product_accommodation_room_amenity (module Product/Catalogue), qui relie le même référentiel ref_amenity à une CHAMBRE précise (contenu commercial, pas descriptif).';

CREATE INDEX idx_ref_accommodation_amenity_accommodation ON ref_accommodation_amenity(accommodation_id);
CREATE INDEX idx_ref_accommodation_amenity_amenity ON ref_accommodation_amenity(amenity_id);

-- ------------------------------------------------------------
CREATE TABLE ref_accommodation_option (
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    option_id         BIGINT NOT NULL REFERENCES ref_option(id),
    PRIMARY KEY (accommodation_id, option_id)
);

COMMENT ON TABLE ref_accommodation_option IS 'Souhaits/demandes gratuites disponibles à cet hôtel (arrivée tardive si possible, chambres communicantes...). Pur lien descriptif.';

CREATE INDEX idx_ref_accommodation_option_accommodation ON ref_accommodation_option(accommodation_id);
CREATE INDEX idx_ref_accommodation_option_option ON ref_accommodation_option(option_id);

-- ------------------------------------------------------------
CREATE TABLE ref_accommodation_location_type_link (
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    location_type_id  BIGINT NOT NULL REFERENCES ref_accommodation_location_type(id),
    PRIMARY KEY (accommodation_id, location_type_id)
);

COMMENT ON TABLE ref_accommodation_location_type_link IS 'Implantation de l''hôtel (Centre-ville/Bord de mer/Palmeraie...). Nommage _link pour éviter la collision avec ref_accommodation_location_type (le référentiel lui-même). Pur lien descriptif -- en pratique souvent 0 ou 1 ligne, mais N-N gardé par cohérence avec les autres liaisons.';

CREATE INDEX idx_ref_accommodation_loctype_accommodation ON ref_accommodation_location_type_link(accommodation_id);
CREATE INDEX idx_ref_accommodation_loctype_type ON ref_accommodation_location_type_link(location_type_id);

-- ------------------------------------------------------------
CREATE TABLE ref_accommodation_tag (
    accommodation_id  BIGINT NOT NULL REFERENCES ref_accommodation(id),
    tag_id             BIGINT NOT NULL REFERENCES ref_tag(id),
    PRIMARY KEY (accommodation_id, tag_id)
);

COMMENT ON TABLE ref_accommodation_tag IS 'Tags de vente / "Services" (vocabulaire métier utilisateur) de l''hôtel. Pur lien descriptif.';

CREATE INDEX idx_ref_accommodation_tag_accommodation ON ref_accommodation_tag(accommodation_id);
CREATE INDEX idx_ref_accommodation_tag_tag ON ref_accommodation_tag(tag_id);

-- ============================================================
-- ref_accommodation_translation : description courte traduisible de
-- l'hôtel entier. Décidée en session Product/Catalogue (18/07/2026),
-- explicitement au niveau hôtel (pas chambre/pension). Additive pure.
-- ============================================================
CREATE TABLE ref_accommodation_translation (
    accommodation_id   BIGINT NOT NULL REFERENCES ref_accommodation(id),
    language_code       VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    short_description    TEXT,
    PRIMARY KEY (accommodation_id, language_code)
);

COMMENT ON TABLE ref_accommodation_translation IS 'Description courte traduisible de l''hôtel entier (FR/EN/AR). Nullable. Ajoutée en réouverture ponctuelle documentée, additive pure sur ref_accommodation.';
-- ============================================================
-- Réouverture ponctuelle documentée (19/07/2026), justifiée par la
-- session Product/Catalogue (sous-module Aérien). Additif pur, aucune
-- table existante de schema-ref-static-v1.sql modifiée ou supprimée.
--
-- Deux entités, deux régimes différents (décision explicite en
-- session) :
--   - ref_cabin_class : classe cabine (Economy/Business...), universelle
--     et fermée -- "ça doit être dans les static data OctaSoft" (mot de
--     l'utilisateur). oct_code NOT NULL, même régime que ref_board_type.
--   - ref_airline_company : compagnie aérienne. Contrairement à
--     ref_cabin_class, l'AJOUT LOCAL est explicitement PERMIS
--     (oct_code NULLABLE, régime ref_tag/ref_hotel_chain) -- cohérent
--     avec le périmètre volontairement réduit du sous-module Aérien
--     (production ponctuelle de billetterie, hors GDS/OctaSoft, pour
--     des compagnies que le référentiel central ne couvre pas
--     forcément).
-- ============================================================

CREATE TABLE ref_cabin_class (
    id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id  UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code   VARCHAR(50) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_cabin_class_public_id ON ref_cabin_class(public_id);
CREATE UNIQUE INDEX uq_ref_cabin_class_oct_code ON ref_cabin_class(oct_code);

COMMENT ON TABLE ref_cabin_class IS 'Classe cabine (Economy/Business/Première...), référentiel unique et fermé, miroir OctaSoft Static Data. Aucun ajout local possible : oct_code NOT NULL -- même régime que ref_board_type. Nom traduit dans ref_cabin_class_translation.';

CREATE TRIGGER trg_ref_cabin_class_updated_at BEFORE UPDATE ON ref_cabin_class
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_cabin_class_translation (
    cabin_class_id  BIGINT NOT NULL REFERENCES ref_cabin_class(id),
    language_code    VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name              VARCHAR(100) NOT NULL,
    PRIMARY KEY (cabin_class_id, language_code)
);

COMMENT ON TABLE ref_cabin_class_translation IS 'Nom de la classe cabine par langue, fourni par OctaSoft Static Data.';

-- ------------------------------------------------------------
CREATE TABLE ref_airline_company (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    oct_code    VARCHAR(50), -- NULLABLE : ajout local permis, contrairement à ref_cabin_class (voir note d'en-tête)
    iata_code   VARCHAR(3),  -- ex: 'TU' (Tunisair) -- pas toujours connu pour un petit opérateur local
    icao_code   VARCHAR(4),
    logo_url    VARCHAR(500),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_airline_company_public_id ON ref_airline_company(public_id);
CREATE UNIQUE INDEX uq_ref_airline_company_oct_code ON ref_airline_company(oct_code) WHERE oct_code IS NOT NULL;
CREATE UNIQUE INDEX uq_ref_airline_company_iata ON ref_airline_company(iata_code) WHERE iata_code IS NOT NULL;

COMMENT ON TABLE ref_airline_company IS 'Compagnie aérienne. Ajout local permis (oct_code NULLABLE) -- contrairement à ref_cabin_class -- cohérent avec le périmètre Aérien volontairement réduit à la production ponctuelle hors GDS. Nom traduit dans ref_airline_company_translation.';

CREATE TRIGGER trg_ref_airline_company_updated_at BEFORE UPDATE ON ref_airline_company
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_airline_company_translation (
    airline_company_id  BIGINT NOT NULL REFERENCES ref_airline_company(id),
    language_code         VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    name                    VARCHAR(150) NOT NULL,
    PRIMARY KEY (airline_company_id, language_code)
);
-- ============================================================
-- Réouverture ponctuelle documentée (19/07/2026), justifiée par la
-- session Pricing -- même mécanisme que ref-static-airline-cabin-
-- extension.diff et ref-static-accommodation-links.diff.
--
-- Origine : le ciblage pays départ/arrivée du moteur de marge vol
-- (Pricing) a besoin de regrouper des pays (ex: "Maghreb", "Europe de
-- l'Ouest") pour cibler plusieurs pays d'un coup dans une règle. Ce
-- concept est de la géographie pure, sans rien de spécifique à la
-- marge -- esquissé d'abord dans pricing_ par erreur, puis relocalisé
-- ici : ref_static est la couche de base dont tout le reste dépend,
-- rien n'y dépend en retour d'un module de plus haut niveau comme
-- Pricing. Réutilisable par d'autres modules référençant déjà
-- ref_country (Visa notamment, futur reporting géographique).
-- ============================================================

CREATE TABLE ref_country_group (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    name        VARCHAR(150) NOT NULL, -- nom interne, pas de traduction (usage back-office uniquement à ce stade)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_country_group_public_id ON ref_country_group(public_id);
CREATE UNIQUE INDEX uq_ref_country_group_name ON ref_country_group(name);

COMMENT ON TABLE ref_country_group IS 'Regroupement de pays réutilisable (ex: "Maghreb", "Europe de l''Ouest"). Introduit pour le ciblage pays départ/arrivée du moteur de marge vol (Pricing, session du 19/07/2026), mais générique -- géographie pure, sans dépendance vers Pricing. Réutilisable par tout module référençant déjà ref_country (Visa notamment).';

CREATE TRIGGER trg_ref_country_group_updated_at BEFORE UPDATE ON ref_country_group
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE ref_country_group_member (
    group_id    BIGINT NOT NULL REFERENCES ref_country_group(id),
    country_id  BIGINT NOT NULL REFERENCES ref_country(id),
    PRIMARY KEY (group_id, country_id)
);

CREATE INDEX idx_ref_country_group_member_country ON ref_country_group_member(country_id);

COMMENT ON TABLE ref_country_group_member IS 'Appartenance pays <-> groupe de pays, N-N (un pays peut appartenir à plusieurs groupes).';
