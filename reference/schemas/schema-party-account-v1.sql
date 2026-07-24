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
-- Version        : 1.5 - Balayage confrontation legacy (24/07/2026) :
--                   devises d'affichage/facturation, comptes comptables
--                   export, exonérations fiscales, affectations de
--                   responsables, plafond/découvert, politique commerciale ;
--                   retrait is_vat_subject (remplacé par
--                   party_account_tax_exemption). Antérieur : V1.4
--                   (franchise 20/07, groupes 19/07) — l'en-tête était
--                   resté en 1.2.
-- Date           : 2026-07-24
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
-- point de vente, RBAC fin, le "vrai" CRM (leads/opportunities/pipeline/
-- activities). Plafond & exonérations : construits ici (balayage 24/07).
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

    -- Devises par défaut (balayage Party 24/07) : DÉFAUTS de saisie et
    -- d'affichage, jamais des contraintes. settlement_balance a pour clé
    -- (compte, rôle, devise) : un même client peut avoir simultanément un
    -- solde en EUR et un solde en USD. La case legacy « Forcer la devise »
    -- est SUPPRIMÉE — elle n'existait que parce que le legacy imposait une
    -- devise unique par client ; cette contrainte n'existe plus.
    display_currency_code  VARCHAR(3) REFERENCES ref_currency(code), -- espace client
    billing_currency_code  VARCHAR(3) REFERENCES ref_currency(code), -- facturation par défaut

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
COMMENT ON COLUMN party_account.display_currency_code IS 'Devise d''affichage préférée (espace client). DÉFAUT de saisie/affichage, jamais une contrainte de solde.';
COMMENT ON COLUMN party_account.billing_currency_code IS 'Devise de facturation proposée par défaut. Distincte de display_currency_code : consulter et être facturé sont deux actes différents.';
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
-- Champs rares / sporadiques -> party_account_attribute (JSONB soupape).
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
    -- Comptes comptables (balayage Party 24/07) : servent UNIQUEMENT à
    -- l'export Excel vers le comptable externe. Aucune écriture comptable
    -- dans le système. L'utilisateur ne connaît pas leur usage exact côté
    -- comptabilité — NE PAS leur inventer de sémantique ni de contrainte.
    -- Nom accounting_account_code aligné sur cash_bank_account.
    accounting_account_code   VARCHAR(30), -- compte collectif (ex '411000')
    third_party_account_code  VARCHAR(30), -- compte tiers individuel
    website          VARCHAR(255),
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by       BIGINT REFERENCES party_account(id),
    updated_by       BIGINT REFERENCES party_account(id)
);

COMMENT ON COLUMN party_account_organization_identity.accounting_account_code IS
'Compte collectif pour export Excel vers le comptable externe UNIQUEMENT. Aucune écriture dans le système — ne pas inventer de sémantique ni de contrainte.';
COMMENT ON COLUMN party_account_organization_identity.third_party_account_code IS
'Compte tiers individuel pour export Excel vers le comptable externe UNIQUEMENT. Aucune écriture dans le système — ne pas inventer de sémantique ni de contrainte.';

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
-- Une ligne par compte. Champs rares / sporadiques qui ne justifient
-- PAS une colonne typée toujours-NULL sur une table jointe en masse
-- (party_account / extensions d'identité). Attribut qui devient
-- stable et interrogé fréquemment -> à promouvoir en colonne typée
-- dans la table d'extension concernée.
-- ⚠️ Ce n'est PAS un EAV générique ni un fourre-tout « autre_config »
-- (rejeté §14) : c'est une soupape volontairement étroite pour éviter
-- de polluer le schéma avec des colonnes quasi jamais renseignées.
-- ============================================================
CREATE TABLE party_account_attribute (
    account_id  BIGINT PRIMARY KEY REFERENCES party_account(id),
    attributes  JSONB NOT NULL DEFAULT '{}'::jsonb,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_attribute IS
'Soupape JSONB pour champs rares d''un compte (éviter des colonnes toujours-NULL sur les tables lues en masse). PAS un EAV générique ni un autre_config — un attribut devenu stable doit être promu en colonne typée.';

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

-- ============================================================
-- EXONÉRATIONS FISCALES (balayage Party 24/07 — ferme §69)
-- Party porte ce qu'on décide SUR un tiers ; Facturation/Règlements
-- LIRONT. Les deux types (TVA, timbre) sont INDÉPENDANTS. Une
-- exonération couvre TOUTE l'activité (pas de dimension service).
-- Remplace party_account_organization_identity.is_vat_subject
-- (supprimé : un particulier peut aussi être exonéré).
-- ============================================================
CREATE TABLE party_tax_exemption_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE party_tax_exemption_type IS 'Types d''exonération fiscale (vat, stamp_duty). Libellés dans party_tax_exemption_type_translation. Modèle A.';

INSERT INTO party_tax_exemption_type (code, sort_order) VALUES
    ('vat',         0),
    ('stamp_duty',  1);

CREATE TABLE party_tax_exemption_type_translation (
    exemption_type_code  VARCHAR(30) NOT NULL REFERENCES party_tax_exemption_type(code),
    language_code        VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label                VARCHAR(100) NOT NULL,
    description          TEXT,
    PRIMARY KEY (exemption_type_code, language_code)
);

INSERT INTO party_tax_exemption_type_translation (exemption_type_code, language_code, label) VALUES
    ('vat',        'en', 'VAT exemption'),
    ('vat',        'fr', 'Exonération de TVA'),
    ('vat',        'ar', 'إعفاء من الأداء على القيمة المضافة'),
    ('stamp_duty', 'en', 'Stamp duty exemption'),
    ('stamp_duty', 'fr', 'Exonération de timbre fiscal'),
    ('stamp_duty', 'ar', 'إعفاء من معلوم الطابع الجبائي');

CREATE TABLE party_account_tax_exemption (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id            UUID NOT NULL DEFAULT gen_random_uuid(),
    account_id           BIGINT NOT NULL REFERENCES party_account(id),
    exemption_type_code  VARCHAR(30) NOT NULL REFERENCES party_tax_exemption_type(code),
    certificate_number   VARCHAR(100), -- figure souvent sur la facture
    document_id          BIGINT REFERENCES party_account_document(id), -- scan, facultatif
    valid_from           DATE NOT NULL,
    valid_to             DATE, -- NULL = exonération permanente ; renseigné = attestation datée
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by           BIGINT REFERENCES party_account(id),

    CONSTRAINT chk_party_account_tax_exemption_period
        CHECK (valid_to IS NULL OR valid_to > valid_from)
);

COMMENT ON TABLE party_account_tax_exemption IS
'Exonération fiscale d''un tiers (TVA ou timbre), indépendantes l''une de l''autre, couvrant toute l''activité. valid_to NULL = permanent ; valid_to renseigné = attestation qui expire. Un renouvellement = NOUVELLE ligne (historique conservé). N''importe quel tiers (person ou organization).';

CREATE UNIQUE INDEX uq_party_account_tax_exemption_public_id ON party_account_tax_exemption(public_id);
CREATE INDEX idx_party_account_tax_exemption_account
    ON party_account_tax_exemption(account_id, exemption_type_code, valid_from);
-- Pas d'index partiel « actif » avec CURRENT_DATE : non IMMUTABLE en PostgreSQL.
-- Le filtrage valid_to IS NULL OR valid_to > current_date se fait en requête.

-- ============================================================
-- AFFECTATIONS DE RESPONSABLES (balayage Party 24/07)
-- DISTINCTE de party_account_function : celle-ci dit « cette personne
-- travaille dans cette organisation » ; celle-là dit « cette personne
-- de chez nous est responsable de ce client ». Affectation GLOBALE
-- (pas par bureau), MULTIPLE par type, historisée (rendement agents §29).
-- ============================================================
CREATE TABLE party_assignment_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE party_assignment_type IS 'Types d''affectation de responsable (commercial, collection). Libellés dans party_assignment_type_translation. Modèle A.';

INSERT INTO party_assignment_type (code, sort_order) VALUES
    ('commercial',  0),
    ('collection',  1);

CREATE TABLE party_assignment_type_translation (
    assignment_type_code  VARCHAR(30) NOT NULL REFERENCES party_assignment_type(code),
    language_code         VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label                 VARCHAR(100) NOT NULL,
    description           TEXT,
    PRIMARY KEY (assignment_type_code, language_code)
);

INSERT INTO party_assignment_type_translation (assignment_type_code, language_code, label) VALUES
    ('commercial', 'en', 'Sales manager'),
    ('commercial', 'fr', 'Responsable commercial'),
    ('commercial', 'ar', 'مسؤول تجاري'),
    ('collection', 'en', 'Collection manager'),
    ('collection', 'fr', 'Responsable recouvrement'),
    ('collection', 'ar', 'مسؤول التحصيل');

CREATE TABLE party_account_manager_assignment (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id           BIGINT NOT NULL REFERENCES party_account(id), -- le client suivi
    manager_account_id   BIGINT NOT NULL REFERENCES party_account(id), -- la personne de l'équipe
    assignment_type_code VARCHAR(30) NOT NULL REFERENCES party_assignment_type(code),
    valid_from           TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to             TIMESTAMPTZ, -- NULL = affectation active
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by           BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_manager_assignment IS
'Affectation historisée d''un responsable (commercial / recouvrement) à un client. Plusieurs responsables par type autorisés. Globale au client, pas par bureau. Un changement de portefeuille ferme la ligne (valid_to) et en crée une nouvelle — jamais d''écrasement.';

CREATE INDEX idx_party_account_manager_assignment_account
    ON party_account_manager_assignment(account_id, assignment_type_code)
    WHERE valid_to IS NULL;
CREATE INDEX idx_party_account_manager_assignment_manager
    ON party_account_manager_assignment(manager_account_id)
    WHERE valid_to IS NULL;

-- ============================================================
-- PLAFOND / AUTORISATION DE DÉCOUVERT (balayage Party 24/07 — ferme §2)
-- UNE SEULE table pour plafond permanent ET rallonge temporaire
-- (valid_to NULL vs renseigné). PAR DEVISE, jamais converti. PAS de
-- ventilation par service (abandonné : dépendrait de la qualité du
-- lettrage). Formule Domain :
--   disponible = solde grand livre (devise) + plafond + rallonges valides
-- ============================================================
CREATE TABLE party_account_credit_limit (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id      UUID NOT NULL DEFAULT gen_random_uuid(),
    account_id     BIGINT NOT NULL REFERENCES party_account(id),
    currency_code  VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    amount_minor   BIGINT NOT NULL CHECK (amount_minor > 0),
    valid_from     TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to       TIMESTAMPTZ, -- NULL = permanent ; renseigné = rallonge qui expire
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by     BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_credit_limit IS
'Autorisation de découvert / plafond par devise. valid_to NULL = permanent ; valid_to renseigné = rallonge temporaire (ex solde temporaire legacy). Plusieurs lignes simultanées OK (plafond + rallonge). Pas de dimension service. Calcul de capacité côté Domain, pas en base.';

CREATE UNIQUE INDEX uq_party_account_credit_limit_public_id ON party_account_credit_limit(public_id);
CREATE INDEX idx_party_account_credit_limit_account
    ON party_account_credit_limit(account_id, currency_code, valid_from);

-- ============================================================
-- POLITIQUE COMMERCIALE PAR COMPTE (balayage Party 24/07)
-- Colonnes explicites TYPÉES, PAS de JSON (§14). Absence de ligne =
-- comportement par défaut. Priorité Domain (base ne fait que stocker) :
--   solde insuffisant + block_when_insufficient_balance -> REFUS
--   solde insuffisant sans la case -> EN DEMANDE, motif insufficient_balance
--   solde suffisant + force_on_request -> EN DEMANDE, motif account_policy
--   solde suffisant sans rien -> confirmation normale
-- ============================================================
CREATE TABLE party_account_commercial_policy (
    account_id                        BIGINT PRIMARY KEY REFERENCES party_account(id),
    force_on_request                  BOOLEAN NOT NULL DEFAULT false,
    block_when_insufficient_balance   BOOLEAN NOT NULL DEFAULT false,
    updated_at                        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_by                        BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_commercial_policy IS
'Politique commerciale d''un compte (colonnes typées, pas de JSON). force_on_request = toujours en demande (motif account_policy). block_when_insufficient_balance = refus si solde insuffisant (ferme DISABLE_PAYMENT_WITHOUT_BALANCE §14). Absence de ligne = défauts.';

CREATE TRIGGER trg_party_account_commercial_policy_updated_at
    BEFORE UPDATE ON party_account_commercial_policy
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
