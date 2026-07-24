-- ============================================================
-- Module         : Party (party_) — anciennement crm_, renommé le 14/07/2026
-- Objet          : Modèle "tiers unifié" (fusion client/fournisseur/
--                   agence/user/passager en une entité pivot avec rôles)
--                   Pattern reconnu en modélisation d'entreprise : "Party"
--                   (Silverston). Brique fondationnelle consommée par
--                   Booking, Invoicing, Contracting ET le futur vrai
--                   module CRM (crm_lead, crm_opportunity, crm_pipeline...)
--                   — d'où la nécessité de ne PAS utiliser le préfixe crm_
--                   ici, pour éviter la collision de nom plus tard.
-- Version        : 1.2 - Renommage crm_->party_, fusion account_member
--                   dans account_function, party_account_address créée,
--                   organization_account_id NOT NULL (plus de NULL magique)
-- Date           : 2026-07-14
-- Réfs           : ADR-010 (PostgreSQL 16), ADR-005 (Politique de disparition —
--                   quatre régimes), ADR-018 (BIGINT Identity + public_id,
--                   précise ADR-008), Objectifs Must-Have Base de Données
--                   (00-project_overview.md : migrations versionnées, argent
--                   en centimes)
-- Dépend de      : core_credential (module core_, voir schema-core-identity-v1.sql)
--                   ref_language + ref_currency (module ref_, voir schema-ref-common.sql) — À EXÉCUTER EN PREMIER
-- ============================================================
-- Tables sources legacy remplacées : ost_amicale, ost_client,
-- ost_com_fournisseur, ost_user (partiellement), sourcecontact
-- Hors périmètre (voir sujets-reportes.md) : pricing/remises/marges,
-- plafond & solde, point de vente, tags commerciaux, RBAC fin,
-- le "vrai" CRM (leads/opportunities/pipeline/activities)
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;  -- gen_random_uuid()
CREATE EXTENSION IF NOT EXISTS pg_trgm;   -- recherche/autocomplete sur display_name

-- ------------------------------------------------------------
-- Fonction générique de mise à jour automatique de updated_at
-- (réutilisée par tous les modules)
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- TABLE DE RÉFÉRENCE : party_role
-- Table (et non ENUM figé) pour ajouter un rôle sans migration lourde
-- ============================================================
CREATE TABLE party_role (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0, -- ordre d'affichage stable en UI (dropdowns...)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE party_role IS 'Référentiel des rôles structurels qu''un tiers peut porter (cumulables, historisés via party_account_role). Libellés dans party_role_translation.';

INSERT INTO party_role (code, sort_order) VALUES
    ('customer',        0),
    ('supplier',   1),
    ('internal_user', 2),
    ('system',        3),
    ('channel',       4);

CREATE TABLE party_role_translation (
    role_code      VARCHAR(30) NOT NULL REFERENCES party_role(code),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(100) NOT NULL,
    description    TEXT,
    PRIMARY KEY (role_code, language_code)
);

INSERT INTO party_role_translation (role_code, language_code, label) VALUES
    ('customer',        'en', 'Client'),
    ('customer',        'fr', 'Client'),
    ('customer',        'ar', 'عميل'),
    ('supplier',   'en', 'Supplier'),
    ('supplier',   'fr', 'Fournisseur'),
    ('supplier',   'ar', 'مورد'),
    ('internal_user', 'en', 'Internal user'),
    ('internal_user', 'fr', 'Utilisateur interne'),
    ('internal_user', 'ar', 'مستخدم داخلي'),
    ('system',        'en', 'System account'),
    ('system',        'fr', 'Compte système'),
    ('system',        'ar', 'حساب نظام'),
    ('channel',       'en', 'Sales channel'),
    ('channel',       'fr', 'Canal de vente'),
    ('channel',       'ar', 'قناة بيع');

-- ============================================================
-- TABLE PIVOT : party_account
-- Identité canonique unique, quel que soit le rôle. Reste volontairement
-- fine car jointe massivement (bookings, factures, réservations...).
-- ============================================================
CREATE TABLE party_account (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(), -- idéalement UUIDv7 généré côté app

    nature             VARCHAR(20) NOT NULL CHECK (nature IN ('person', 'organization')),
    parent_account_id  BIGINT REFERENCES party_account(id), -- sous-comptes (agences mères -> sous-agences)

    -- Identité commune
    display_name       VARCHAR(255) NOT NULL,
    email              VARCHAR(255),
    phone_primary      VARCHAR(50),
    phone_secondary    VARCHAR(50),
    country_id         BIGINT, -- FK vers référentiel statique pays (ex ost_sht_pays), hors périmètre de ce script

    -- États indépendants et orthogonaux (choix assumé : pas de state machine imposée)
    is_disabled        BOOLEAN NOT NULL DEFAULT false,
    is_prospect        BOOLEAN NOT NULL DEFAULT false,
    is_disputed        BOOLEAN NOT NULL DEFAULT false, -- ex ancien "agence_contentieux"

    -- Cache de lecture (dénormalisation assumée pour la performance, cf. note)
    logo_url           VARCHAR(500), -- copie du file_path du document actif de type 'logo'

    -- Audit trail (ADR-011)
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at         TIMESTAMPTZ,
    created_by         BIGINT REFERENCES party_account(id),
    updated_by         BIGINT REFERENCES party_account(id),

    CONSTRAINT ck_party_account_email_format
        CHECK (email IS NULL OR email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')
);

COMMENT ON TABLE party_account IS 'Tiers unifié (pattern "Party") : remplace ost_amicale + ost_client + ost_com_fournisseur + partie identité de ost_user. NOTE ADRESSE : voir party_account_address, plus de champ adresse plat ici (besoin multi-adresses confirmé par l''historique ost_client : adresse/delivery_adresse/adressedomiciliation).';
COMMENT ON COLUMN party_account.nature IS 'Nature juridique : person ou organization. Détermine quelle table identity est renseignée.';
COMMENT ON COLUMN party_account.parent_account_id IS 'Sous-compte B2B : agence mère gère plafond/pricing de ses sous-agences (logique déléguée au module Pricing/Finance)';
COMMENT ON CONSTRAINT ck_party_account_email_format ON party_account IS 'Garde-fou DB uniquement (défense en profondeur) — la validation métier réelle vit dans le Domain layer.';

CREATE UNIQUE INDEX uq_party_account_email_active ON party_account (lower(email))
    WHERE deleted_at IS NULL AND email IS NOT NULL;
CREATE UNIQUE INDEX uq_party_account_public_id ON party_account(public_id);
CREATE INDEX idx_party_account_parent ON party_account(parent_account_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_party_account_active ON party_account(id) WHERE deleted_at IS NULL;
CREATE INDEX idx_party_account_nature ON party_account(nature) WHERE deleted_at IS NULL;
CREATE INDEX idx_party_account_display_name_trgm ON party_account USING GIN (display_name gin_trgm_ops);

CREATE TRIGGER trg_party_account_updated_at BEFORE UPDATE ON party_account
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- party_address_type : types d'adresse (modèle A, traduit)
-- ============================================================
CREATE TABLE party_address_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE party_address_type IS 'Types d''adresse d''un tiers (legal/billing/delivery/domiciliation/other). Libellés dans party_address_type_translation.';

INSERT INTO party_address_type (code, sort_order) VALUES
    ('legal',          0),
    ('billing',        1),
    ('delivery',       2),
    ('domiciliation',  3),
    ('other',          4);

CREATE TABLE party_address_type_translation (
    address_type_code  VARCHAR(30) NOT NULL REFERENCES party_address_type(code),
    language_code      VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label              VARCHAR(100) NOT NULL,
    description        TEXT,
    PRIMARY KEY (address_type_code, language_code)
);

INSERT INTO party_address_type_translation (address_type_code, language_code, label) VALUES
    ('legal',         'en', 'Legal'),
    ('legal',         'fr', 'Légale'),
    ('legal',         'ar', 'قانونية'),
    ('billing',       'en', 'Billing'),
    ('billing',       'fr', 'Facturation'),
    ('billing',       'ar', 'فوترة'),
    ('delivery',      'en', 'Delivery'),
    ('delivery',      'fr', 'Livraison'),
    ('delivery',      'ar', 'تسليم'),
    ('domiciliation', 'en', 'Domiciliation'),
    ('domiciliation', 'fr', 'Domiciliation'),
    ('domiciliation', 'ar', 'موطن'),
    ('other',         'en', 'Other'),
    ('other',         'fr', 'Autre'),
    ('other',         'ar', 'أخرى');

-- ============================================================
-- party_account_address : adresses multi-valeurs, historisées
-- Besoin confirmé par l'historique legacy (ost_client avait 3 adresses
-- distinctes : adresse, delivery_adresse, adressedomiciliation).
-- ============================================================
CREATE TABLE party_account_address (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id   BIGINT NOT NULL REFERENCES party_account(id),
    address_type VARCHAR(30) NOT NULL REFERENCES party_address_type(code),
    line1        VARCHAR(255) NOT NULL,
    line2        VARCHAR(255),
    city         VARCHAR(100),
    postal_code  VARCHAR(20),
    country_id   BIGINT, -- FK référentiel statique pays, hors périmètre de ce script
    is_primary   BOOLEAN NOT NULL DEFAULT false,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at   TIMESTAMPTZ,
    created_by   BIGINT REFERENCES party_account(id),
    updated_by   BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_address IS 'Un compte peut avoir plusieurs adresses typées (legal/billing/delivery/domiciliation). Une seule primary par type.';

CREATE INDEX idx_party_address_account ON party_account_address(account_id) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX uq_party_address_primary_per_type
    ON party_account_address(account_id, address_type)
    WHERE is_primary = true AND deleted_at IS NULL;

CREATE TRIGGER trg_party_account_address_updated_at BEFORE UPDATE ON party_account_address
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- party_account_role : association historisée account <-> rôle
-- Un compte peut cumuler plusieurs rôles et changer dans le temps
-- ============================================================
CREATE TABLE party_account_role (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id  BIGINT NOT NULL REFERENCES party_account(id),
    role_code   VARCHAR(30) NOT NULL REFERENCES party_role(code),
    valid_from  TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to    TIMESTAMPTZ, -- NULL = rôle actif
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_role IS 'Historique des rôles portés par un compte. Un même compte peut être client ET fournisseur simultanément.';

CREATE UNIQUE INDEX uq_party_account_role_active ON party_account_role(account_id, role_code) WHERE valid_to IS NULL;
CREATE INDEX idx_party_account_role_lookup ON party_account_role(role_code, account_id) WHERE valid_to IS NULL;

-- ============================================================
-- party_account_person_identity : extension 1-1, nature = person
-- Volontairement minimale : uniquement ce qui a du sens pour TOUT
-- compte de nature person, sans exception rare.
-- CIN/passeport/permis -> party_account_document (cycle de vie propre).
-- birth_date/marriage_date -> party_account_attribute (JSONB, usage rare).
-- ============================================================
CREATE TABLE party_account_person_identity (
    account_id  BIGINT PRIMARY KEY REFERENCES party_account(id),
    first_name  VARCHAR(150),
    last_name   VARCHAR(150),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id),
    updated_by  BIGINT REFERENCES party_account(id)
);

CREATE TRIGGER trg_party_person_identity_updated_at BEFORE UPDATE ON party_account_person_identity
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- party_account_organization_identity : extension 1-1, nature = organization
-- ============================================================
CREATE TABLE party_account_organization_identity (
    account_id       BIGINT PRIMARY KEY REFERENCES party_account(id),
    tax_id           VARCHAR(50),   -- ex matriculeFiscale
    trade_register   VARCHAR(50),   -- ex registreCommercie
    legal_form_code  VARCHAR(30),   -- vers référentiel dédié à créer si besoin (voir sujets-reportes.md)
    is_vat_subject   BOOLEAN NOT NULL DEFAULT false,
    website          VARCHAR(255),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by       BIGINT REFERENCES party_account(id),
    updated_by       BIGINT REFERENCES party_account(id)
);

CREATE TRIGGER trg_party_org_identity_updated_at BEFORE UPDATE ON party_account_organization_identity
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- party_function : référentiel des fonctions métier (table, pas ENUM)
-- Distinct de party_role : le rôle dit ce qu'EST le compte (client/
-- fournisseur/interne), la fonction dit ce que FAIT la personne au
-- quotidien — ET fait aussi office d'autorisation d'accès de base
-- (fusion avec l'ancienne party_account_member, cf. décision du 14/07/2026 :
-- une personne "membre sans fonction précise" porte simplement la
-- fonction générique 'member').
-- ============================================================
CREATE TABLE party_function (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE party_function IS 'Fonctions métier exercées par une personne (Gérant, Financier, Contracting, Agent de réservation, Membre générique...), cumulables et contextualisées par organisation. Libellés dans party_function_translation.';

INSERT INTO party_function (code, sort_order) VALUES
    ('member',            0), -- accès de base, sans fonction métier précise (ex-party_account_member)
    ('manager',            1),
    ('finance',         2),
    ('contracting',       3),
    ('booking_agent', 4);

CREATE TABLE party_function_translation (
    function_code  VARCHAR(30) NOT NULL REFERENCES party_function(code),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(100) NOT NULL,
    description    TEXT,
    PRIMARY KEY (function_code, language_code)
);

INSERT INTO party_function_translation (function_code, language_code, label) VALUES
    ('member',            'en', 'Member'),
    ('member',            'fr', 'Membre'),
    ('member',            'ar', 'عضو'),
    ('manager',            'en', 'Manager'),
    ('manager',            'fr', 'Gérant'),
    ('manager',            'ar', 'مدير'),
    ('finance',         'en', 'Finance'),
    ('finance',         'fr', 'Financier'),
    ('finance',         'ar', 'مالية'),
    ('contracting',       'en', 'Contracting'),
    ('contracting',       'fr', 'Contracting'),
    ('contracting',       'ar', 'التعاقد'),
    ('booking_agent', 'en', 'Booking agent'),
    ('booking_agent', 'fr', 'Agent de réservation'),
    ('booking_agent', 'ar', 'وكيل حجز');

-- ============================================================
-- party_account_function : attribution historisée d'une fonction à une
-- personne, dans un contexte organisationnel donné.
-- organization_account_id est TOUJOURS renseigné (plus de NULL magique,
-- cf. décision du 14/07/2026) : le contexte "interne" pointe vers le
-- party_account représentant l'agence exploitant la plateforme elle-même
-- (à créer lors du bootstrap, voir note en bas de fichier).
-- Fusionne l'ancienne party_account_member : porter la fonction 'member'
-- équivaut à "peut agir pour cette organisation, sans fonction précise".
-- Une personne peut cumuler plusieurs fonctions (ex: Gérant + Financier
-- dans une petite agence).
-- ============================================================
CREATE TABLE party_account_function (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    person_account_id        BIGINT NOT NULL REFERENCES party_account(id), -- la personne
    organization_account_id  BIGINT NOT NULL REFERENCES party_account(id), -- l'organisation concernée (jamais NULL)
    function_code            VARCHAR(30) NOT NULL REFERENCES party_function(code),
    valid_from                TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to                  TIMESTAMPTZ,
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_function IS 'Ancrage pour les droits (core_function_permission, à venir) et le routage email (service Notifications, hors périmètre party_/core_). Table unique pour accès ET fonction métier (fusion actée le 14/07/2026).';

CREATE UNIQUE INDEX uq_party_account_function_active
    ON party_account_function(person_account_id, function_code, organization_account_id)
    WHERE valid_to IS NULL;

CREATE INDEX idx_party_account_function_person ON party_account_function(person_account_id) WHERE valid_to IS NULL;
CREATE INDEX idx_party_account_function_org ON party_account_function(organization_account_id) WHERE valid_to IS NULL;
CREATE INDEX idx_party_account_function_code ON party_account_function(function_code) WHERE valid_to IS NULL;

-- ============================================================
-- party_account_attribute : soupape anti-dette technique (JSONB)
-- Une ligne par compte. Contient notamment birth_date/marriage_date
-- (usage trop rare pour justifier une colonne dédiée en V1).
-- Attribut qui devient stable/interrogé fréquemment -> à promouvoir
-- en colonne typée dans la table d'extension concernée.
-- ============================================================
CREATE TABLE party_account_attribute (
    account_id  BIGINT PRIMARY KEY REFERENCES party_account(id),
    attributes  JSONB NOT NULL DEFAULT '{}'::jsonb,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_by  BIGINT REFERENCES party_account(id)
);

CREATE INDEX idx_party_account_attribute_gin ON party_account_attribute USING GIN (attributes);

CREATE TRIGGER trg_party_account_attribute_updated_at BEFORE UPDATE ON party_account_attribute
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- party_account_document : pièces jointes ET documents d'identité
-- Un compte peut avoir plusieurs documents du même type dans le temps
-- (ex: passeports successifs). Le document "courant" se détermine par
-- expiry_date NULL ou > now(), pas par une colonne de statut figée.
-- ============================================================
CREATE TABLE party_account_document (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id          BIGINT NOT NULL REFERENCES party_account(id),
    document_type       VARCHAR(30) NOT NULL REFERENCES ref_document_type(code),
    document_number     VARCHAR(100),          -- numéro (passeport/CIN/permis...), NULL si non applicable
    issuing_country_id  BIGINT,                -- FK référentiel pays statique, NULL si non applicable
    issue_date          DATE,
    expiry_date         DATE,                  -- NULL = sans expiration
    file_path           VARCHAR(500),
    metadata            JSONB NOT NULL DEFAULT '{}'::jsonb, -- attributs rares spécifiques à un type de document
    uploaded_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    uploaded_by         BIGINT REFERENCES party_account(id),
    deleted_at          TIMESTAMPTZ
);

COMMENT ON TABLE party_account_document IS 'Documents et pièces d''identité versionnés dans le temps (ex: renouvellement de passeport = nouvelle ligne, historique conservé).';

CREATE INDEX idx_party_document_account_type ON party_account_document(account_id, document_type) WHERE deleted_at IS NULL;
CREATE INDEX idx_party_document_expiry ON party_account_document(expiry_date) WHERE deleted_at IS NULL AND expiry_date IS NOT NULL;

-- ============================================================
-- party_account_office : extension 1-1, marque qu'un party_account
-- (nature=organization) est un bureau opérationnel de l'agence
-- (ex: bureau Tunisie, bureau Algérie, bureau France...), avec sa
-- propre identité légale (via party_account_organization_identity)
-- et sa devise de fonctionnement par défaut.
-- Un "bureau" = une entité juridique par pays, avec sa propre
-- identification fiscale. Les sites physiques secondaires dans le
-- même pays (ex: succursale ville) ne sont PAS des party_account
-- séparés -> concept "point de vente" (reporté, voir sujets-reportes.md
-- point 3), rattaché à ce party_account_office quand il sera conçu.
-- ============================================================
CREATE TABLE party_account_office (
    account_id              BIGINT PRIMARY KEY REFERENCES party_account(id), -- doit être nature='organization' (règle applicative)
    office_code              VARCHAR(20) NOT NULL,  -- ex: 'TN','DZ','FR'...
    country_id                BIGINT,                -- FK référentiel statique pays, hors périmètre de ce script
    default_currency_code       VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    created_at                    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                      TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                        BIGINT REFERENCES party_account(id),
    updated_by                          BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_office IS 'Bureau opérationnel de l''agence (entité légale par pays). Un party_account de nature organization peut être à la fois un tiers classique ET un bureau (ex: bureau DZ peut être client du bureau TN).';

CREATE UNIQUE INDEX uq_party_account_office_code ON party_account_office(office_code);

CREATE TRIGGER trg_party_account_office_updated_at BEFORE UPDATE ON party_account_office
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- party_account_office_relation : lien approuvé et historisé entre un
-- tiers et un bureau. Obligatoire avant toute transaction (Booking) :
-- un client ne peut acheter que s'il est officiellement rattaché à un
-- bureau après approbation. relation_type = rôle du TIERS (account_id)
-- vis-à-vis du bureau : 'customer' (le tiers achète auprès du bureau) ou
-- 'supplier' (le tiers fournit le bureau). Cumulable : une agence
-- affiliée B2B peut être client de plusieurs bureaux à la fois.
-- ============================================================
CREATE TABLE party_account_office_relation (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id          BIGINT NOT NULL REFERENCES party_account(id), -- le tiers (client ou fournisseur)
    office_account_id     BIGINT NOT NULL REFERENCES party_account(id), -- le bureau concerné (porte party_account_office)
    relation_type            VARCHAR(30) NOT NULL REFERENCES party_role(code)
                                  CHECK (relation_type IN ('customer', 'supplier')),
    is_approved                  BOOLEAN NOT NULL DEFAULT false,
    approved_at                     TIMESTAMPTZ,
    approved_by                        BIGINT REFERENCES party_account(id),
    valid_from                            TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to                                TIMESTAMPTZ,
    created_at                                TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                                  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_office_relation IS 'Rattachement obligatoire tiers<->bureau, avec workflow d''approbation. La vérification "peut acheter" (is_approved=true, valid_to NULL) est portée par le futur module Booking, pas ici.';

CREATE UNIQUE INDEX uq_party_office_relation_active
    ON party_account_office_relation(account_id, office_account_id, relation_type)
    WHERE valid_to IS NULL;

CREATE INDEX idx_party_office_relation_account ON party_account_office_relation(account_id) WHERE valid_to IS NULL;
CREATE INDEX idx_party_office_relation_office ON party_account_office_relation(office_account_id) WHERE valid_to IS NULL;

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. country_id / issuing_country_id référencent une future table 'country'
--    statique (issue de ost_sht_pays), hors périmètre de ce script.
-- 2. public_id : générer idéalement un UUIDv7 (ordonné temporellement)
--    côté application ; gen_random_uuid() (v4) utilisé ici comme fallback DB.
--    Pattern BIGINT+public_id justifié dans ADR-018 (précise ADR-008).
-- 3. Règle "parent_account_id uniquement pour nature=organization" :
--    appliquée en couche Application/Domain, pas en CHECK SQL pur.
-- 4. Règles métier plafond/pricing des sous-comptes : gérées par le futur
--    module Pricing/Finance, hors périmètre party_.
-- 5. party_account_group / tags commerciaux : conception actée, implémentation
--    reportée (voir sujets-reportes.md).
-- 6. L'authentification (login/password/OAuth) vit dans le module core_
--    (voir schema-core-identity-v1.sql), pas dans party_.
-- 7. logo_url sur party_account est un cache dénormalisé (dénormalisation
--    justifiée pour la performance sur un chemin de lecture très fréquent).
--    Source de vérité = party_account_document (document_type='logo').
--    Synchronisation : responsabilité de la couche Application au moment
--    de l'upload (pas de trigger DB, cf. logique métier hors DB).
-- 8. party_role/party_function : libellés déplacés dans party_role_translation/
--    party_function_translation (voir dépendance ref_language ci-dessus).
-- 9. Dépendance : ce script requiert schema-ref-common.sql (table
--    ref_language) exécuté au préalable.
-- 10. BOOTSTRAP OBLIGATOIRE : avant tout autre insert applicatif, créer
--    la ligne party_account représentant l'agence exploitant la plateforme
--    elle-même (nature='organization', ex: display_name = raison sociale
--    réelle). Utiliser son id comme organization_account_id pour toute
--    party_account_function de contexte "interne" (staff back-office).
--    Ne jamais utiliser NULL comme substitut — cette valeur nourrira aussi,
--    plus tard, l'en-tête légal des factures (module Facturation).
-- 11. Fusion party_account_member -> party_account_function (14/07/2026) :
--    l'ancienne notion d'accès pur sans fonction devient la fonction
--    générique 'member'. Réduit une table, une redondance d'écriture.
-- 12. party_account_office : un "bureau" = entité légale par pays (tax_id
--    propre). Les sites physiques secondaires (villes) ne sont PAS des
--    party_account -> futur concept "point de vente", rattaché à un
--    party_account_office (voir sujets-reportes.md point 3).
-- 13. party_account_office_relation.office_account_id doit référencer un
--    party_account portant party_account_office -> règle applicative,
--    pas une contrainte SQL (même logique que la note 3 sur nature).
-- ============================================================
-- Réouverture ponctuelle documentée (19/07/2026), justifiée par la
-- session Pricing : ferme le point 4 de sujets-reportes.md
-- ("party_account_group — tags/segmentation commerciale"), resté en
-- implémentation reportée depuis la conception initiale de Party
-- (14/07/2026). Additif pur, party_account elle-même non touchée.
--
-- Origine du déclenchement : le moteur de marge (Pricing) a besoin de
-- cibler des groupes d'affiliés partageant les mêmes règles (ex:
-- "Groupe Amicale 1", "Top Partners", "Gros compte", "Petits comptes").
-- Ce concept avait déjà été identifié et nommé dans la conception
-- initiale de Party, jamais implémenté -- Pricing en a besoin
-- maintenant, donc on ferme le point ici plutôt que de construire un
-- concept concurrent propre à Pricing.
--
-- Décisions prises en session pour lever les deux inconnues du point 4
-- ("group_type : une dimension ou plusieurs superposées ?",
-- "administration des groupes") :
--   - group_type : PLUSIEURS dimensions superposées dès maintenant
--     (référentiel party_account_group_type séparé) -- un compte peut
--     appartenir à des groupes de types différents simultanément (ex:
--     un groupe "commercial" ET un groupe "zone" en même temps). Aucun
--     coût si une seule dimension est utilisée en pratique ; évite une
--     migration lourde si un deuxième axe apparaît plus tard. Seule la
--     dimension 'commercial' est peuplée dans cette session (besoin
--     Pricing confirmé) -- les autres dimensions seront ajoutées comme
--     simples lignes de référentiel, sans migration.
--   - Historisation : même pattern que party_account_role
--     (valid_from/valid_to, index partiel sur l'appartenance active) --
--     pas une simple table N-N sans historique.
--   - Administration des groupes : reste hors périmètre de cette
--     réouverture (pas de règle de gouvernance/permission posée ici,
--     uniquement la structure de données).
-- ============================================================

CREATE TABLE party_account_group_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO party_account_group_type (code, sort_order) VALUES
    ('commercial', 0);

COMMENT ON TABLE party_account_group_type IS 'Dimensions de regroupement de comptes, superposables (un compte peut appartenir à des groupes de plusieurs types simultanément). Seule la dimension ''commercial'' est peuplée à ce stade (besoin Pricing, session du 19/07/2026) -- extensible par simple ajout de ligne, sans migration.';

-- ------------------------------------------------------------
CREATE TABLE party_account_group (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id        UUID NOT NULL DEFAULT gen_random_uuid(),
    group_type_code  VARCHAR(30) NOT NULL REFERENCES party_account_group_type(code),
    name             VARCHAR(150) NOT NULL, -- nom interne, pas de traduction (usage back-office uniquement)
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_party_account_group_public_id ON party_account_group(public_id);
CREATE UNIQUE INDEX uq_party_account_group_type_name ON party_account_group(group_type_code, name);

COMMENT ON TABLE party_account_group IS 'Regroupement de comptes réutilisable (ex: "Groupe Amicale 1", "Top Partners"), scopé par dimension (group_type_code). Ferme le point 4 de sujets-reportes.md. Premier usage : ciblage de règles de marge (Pricing), potentiellement réutilisable pour du reporting/statistiques plus tard.';

CREATE TRIGGER trg_party_account_group_updated_at BEFORE UPDATE ON party_account_group
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ------------------------------------------------------------
-- party_account_group_member : appartenance historisée, même pattern
-- que party_account_role (valid_from/valid_to, index partiel actif).
-- Un compte peut appartenir à PLUSIEURS groupes simultanément, y
-- compris de la même dimension (ex: deux groupes "commercial" en même
-- temps) -- aucune contrainte d'exclusivité posée, confirmé nécessaire
-- par l'usage Pricing (un affilié peut être ciblé par plusieurs
-- groupes à la fois dans une même règle).
-- ------------------------------------------------------------
CREATE TABLE party_account_group_member (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id  BIGINT NOT NULL REFERENCES party_account(id),
    group_id    BIGINT NOT NULL REFERENCES party_account_group(id),
    valid_from  TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to    TIMESTAMPTZ, -- NULL = appartenance active
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_group_member IS 'Appartenance compte <-> groupe, historisée (même pattern que party_account_role). Un compte peut appartenir à plusieurs groupes simultanément, y compris de la même dimension.';

CREATE UNIQUE INDEX uq_party_account_group_member_active ON party_account_group_member(account_id, group_id) WHERE valid_to IS NULL;
CREATE INDEX idx_party_account_group_member_lookup ON party_account_group_member(group_id, account_id) WHERE valid_to IS NULL;
