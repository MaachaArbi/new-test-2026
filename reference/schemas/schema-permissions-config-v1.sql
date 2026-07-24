-- ============================================================
-- Module         : Permissions dynamiques (RBAC opt-in inversé, ADR-017)
-- Objet          : Catalogue de permissions granulaires, catégories
--                   arborescentes, rôles applicatifs (groupes de
--                   permissions), attribution par rôle OU directe sur
--                   compte (exception), plafond de délégation.
-- Version        : 1.0
-- Dépend de      : party_account (module party_)
-- ============================================================

-- ============================================================
-- core_permission_category : arborescence purement organisationnelle
-- (aide navigation écran d'admin des droits, ex: "Module Commercial" >
-- sous-éléments). AUCUN impact sur la logique de contrôle d'accès --
-- denyUnlessGranted() ne référence jamais la catégorie, seulement le
-- code de la permission. Confirmé sur capture legacy (arbre de
-- modules > permissions unitaires).
-- ============================================================
CREATE TABLE core_permission_category (
    code        VARCHAR(50) PRIMARY KEY,
    parent_code VARCHAR(50) REFERENCES core_permission_category(code),
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE core_permission_category IS 'Arborescence organisationnelle des permissions (ex: Module Commercial > sous-catégories), auto-référencée. Purement UI/navigation -- aucun impact sur la résolution de droits.';

CREATE TABLE core_permission_category_translation (
    category_code  VARCHAR(50) NOT NULL REFERENCES core_permission_category(code),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(150) NOT NULL,
    PRIMARY KEY (category_code, language_code)
);

-- ============================================================
-- core_permission : catalogue des actions gatées (ADR-017 -- une
-- action devient FERMÉE par défaut pour tout le monde dès qu'une
-- ligne existe ici ; silence = ouvert tant qu'aucune ligne n'existe
-- pour cette action précise). is_delegable : plafond fixe, valable
-- pour toute franchise/agence B2B en auto-gestion (décidé en session --
-- ex: saisie contrat/facturation/ouverture caisse = false).
-- ============================================================
CREATE TABLE core_permission (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id       UUID NOT NULL DEFAULT gen_random_uuid(),
    code            VARCHAR(100) NOT NULL, -- ex: 'crm.customer.create', 'core.user.manage'
    category_code   VARCHAR(50) REFERENCES core_permission_category(code),
    is_delegable    BOOLEAN NOT NULL DEFAULT false,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by      BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE core_permission IS 'Catalogue des permissions dynamiques (ADR-017). Une ligne ici = action fermée par défaut. is_delegable=false = jamais attribuable par un admin délégué (franchise/B2B en auto-gestion), quel que soit son propre droit -- plafond fixe et universel, pas configurable par franchise.';

CREATE UNIQUE INDEX uq_core_permission_code ON core_permission(code);
CREATE UNIQUE INDEX uq_core_permission_public_id ON core_permission(public_id);
CREATE INDEX idx_core_permission_category ON core_permission(category_code);

CREATE TABLE core_permission_translation (
    permission_id  BIGINT NOT NULL REFERENCES core_permission(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(200) NOT NULL,
    PRIMARY KEY (permission_id, language_code)
);

-- ============================================================
-- core_role : "rôle applicatif" = groupe de permissions (le "groupe"
-- du legacy), DISTINCT de party_role (rôle structurel du tiers,
-- client/fournisseur/franchise...). Table extensible sans migration --
-- un client (ou une franchise/B2B en auto-gestion, sous réserve du
-- plafond is_delegable) peut créer ses propres rôles.
-- ============================================================
CREATE TABLE core_role (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id       UUID NOT NULL DEFAULT gen_random_uuid(),
    code            VARCHAR(100) NOT NULL,
    label           VARCHAR(150) NOT NULL,
    owner_account_id BIGINT REFERENCES party_account(id), -- NULL = rôle système/global fourni par défaut ; renseigné = créé par une organisation pour sa propre auto-gestion (franchise/B2B)
    is_active       BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by      BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE core_role IS 'Rôle applicatif = groupe de permissions nommé (distinct de party_role, rôle structurel du tiers). owner_account_id NULL = rôle global fourni par défaut ; renseigné = rôle propre à une organisation (franchise/B2B auto-gérée).';

CREATE UNIQUE INDEX uq_core_role_public_id ON core_role(public_id);
CREATE UNIQUE INDEX uq_core_role_code_owner ON core_role(code, COALESCE(owner_account_id, 0));
CREATE INDEX idx_core_role_owner ON core_role(owner_account_id) WHERE owner_account_id IS NOT NULL;

CREATE TRIGGER trg_core_role_updated_at BEFORE UPDATE ON core_role
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- core_role_permission : quelles permissions un rôle applicatif porte.
-- ============================================================
CREATE TABLE core_role_permission (
    role_id       BIGINT NOT NULL REFERENCES core_role(id),
    permission_id BIGINT NOT NULL REFERENCES core_permission(id),
    granted_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    granted_by    BIGINT REFERENCES party_account(id),
    PRIMARY KEY (role_id, permission_id)
);

COMMENT ON TABLE core_role_permission IS 'Composition d''un rôle applicatif -- ensemble de permissions qu''il porte. Retrait = DELETE (pas d''historisation ligne à ligne ; l''historique global de la composition d''un rôle n''est pas un besoin confirmé en session).';

-- ============================================================
-- core_account_role : attribution historisée d'un rôle applicatif à
-- un compte. Cumulable (plusieurs rôles actifs simultanément), même
-- pattern que party_account_role/party_account_function.
-- ============================================================
CREATE TABLE core_account_role (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id  BIGINT NOT NULL REFERENCES party_account(id),
    role_id     BIGINT NOT NULL REFERENCES core_role(id),
    valid_from  TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to    TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE core_account_role IS 'Attribution historisée d''un rôle applicatif à un compte, cumulable. Append-only (clôture par valid_to, jamais d''UPDATE sur le contenu).';

CREATE UNIQUE INDEX uq_core_account_role_active ON core_account_role(account_id, role_id) WHERE valid_to IS NULL;
CREATE INDEX idx_core_account_role_account ON core_account_role(account_id) WHERE valid_to IS NULL;

-- ============================================================
-- core_permission_grant : attribution directe d'une permission, soit à
-- un rôle applicatif (voie principale, gros volume), soit directement
-- à un compte (exception, cas ponctuel -- évite de fabriquer un rôle
-- jetable pour un seul utilisateur). Jamais les deux sur la même ligne
-- (XOR strict via CHECK).
-- ============================================================
CREATE TABLE core_permission_grant (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    permission_id  BIGINT NOT NULL REFERENCES core_permission(id),
    role_id        BIGINT REFERENCES core_role(id),
    account_id     BIGINT REFERENCES party_account(id),
    valid_from     TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to       TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by     BIGINT REFERENCES party_account(id),

    CONSTRAINT chk_core_permission_grant_xor
        CHECK ( (role_id IS NOT NULL AND account_id IS NULL)
             OR (role_id IS NULL AND account_id IS NOT NULL) )
);

COMMENT ON TABLE core_permission_grant IS 'Octroi d''une permission -- soit à un rôle applicatif (voie principale), soit directement à un compte (exception ponctuelle). XOR strict (chk_core_permission_grant_xor). La vérification is_delegable (plafond) est une règle Application, pas un CHECK ici -- dépend de qui accorde, cross-table (ADR-002).';

CREATE UNIQUE INDEX uq_core_permission_grant_role_active
    ON core_permission_grant(permission_id, role_id) WHERE valid_to IS NULL AND role_id IS NOT NULL;
CREATE UNIQUE INDEX uq_core_permission_grant_account_active
    ON core_permission_grant(permission_id, account_id) WHERE valid_to IS NULL AND account_id IS NOT NULL;
CREATE INDEX idx_core_permission_grant_permission ON core_permission_grant(permission_id) WHERE valid_to IS NULL;
CREATE INDEX idx_core_permission_grant_account ON core_permission_grant(account_id) WHERE valid_to IS NULL AND account_id IS NOT NULL;
-- ============================================================
-- Module : Configuration avancée — Documents & Emails (document_)
-- ============================================================

-- ============================================================
-- document_context_type : contexte métier dans lequel un template
-- peut se déclencher. 'booking' construit (dimensions ci-dessous).
-- 'invoicing' seedé mais SANS dimension de condition construite --
-- déclenchement uniquement sur événement brut ("facture créée"),
-- aucun filtre affiné tant qu'aucun besoin réel ne se présente
-- (décision explicite en session, évite le travail spéculatif).
-- ============================================================
CREATE TABLE document_context_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0
);

INSERT INTO document_context_type (code, sort_order) VALUES
    ('booking',   0),
    ('invoicing', 1),
    ('none',      2); -- pour trigger_mode='system_event' (inscription, reset password...) : aucun contexte de condition

-- ============================================================
-- document_type : catalogue extensible (client peut créer un nouveau
-- type, doit choisir parmi les composants/contextes déjà existants --
-- pas de nouvelle dimension inventable sans développeur, décision
-- actée en session).
-- ============================================================
CREATE TABLE document_type (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id   UUID NOT NULL DEFAULT gen_random_uuid(),
    code        VARCHAR(50) NOT NULL,
    output_kind VARCHAR(10) NOT NULL CHECK (output_kind IN ('email', 'pdf')), -- rendu final -- email = HTML direct, pdf = HTML converti (infra hors périmètre schéma)
    owner_account_id BIGINT REFERENCES party_account(id), -- NULL = type système fourni par défaut ; renseigné = créé par un client
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

CREATE UNIQUE INDEX uq_document_type_public_id ON document_type(public_id);
CREATE UNIQUE INDEX uq_document_type_code_owner ON document_type(code, COALESCE(owner_account_id, 0));

COMMENT ON TABLE document_type IS 'Catalogue extensible de types de documents/emails (voucher, contrat, email réservation hôtel, reset password...). owner_account_id NULL = fourni par défaut à l''installation.';

-- ============================================================
-- document_component_type : composants pré-calculés côté Domain
-- (ex: "statut_reservation", "chambres_et_occupants"), catalogue
-- FERMÉ (extensible uniquement par nouvelle version applicative --
-- décision actée : pas de logique arbitraire écrite par le client).
-- ============================================================
CREATE TABLE document_component_type (
    code               VARCHAR(60) PRIMARY KEY,
    applicable_context  VARCHAR(30) REFERENCES document_context_type(code), -- quel contexte peut l'utiliser (NULL = universel, ex: nom_client)
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE document_component_type_translation (
    component_code VARCHAR(60) NOT NULL REFERENCES document_component_type(code),
    language_code   VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label           VARCHAR(150) NOT NULL,
    PRIMARY KEY (component_code, language_code)
);

-- ============================================================
-- document_template : noyau, un template = une version. Une seule
-- ACTIVE à la fois par document_type (index unique partiel -- invariant
-- garanti par la base, pas seulement par discipline applicative).
-- trigger_mode distingue business_rule (contexte réservation/facture,
-- composable par le client) de system_event (code appelant direct,
-- to non configurable). is_active peut être false même pour
-- system_event (confirmé en session).
-- ============================================================
CREATE TABLE document_template (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id         UUID NOT NULL DEFAULT gen_random_uuid(),
    document_type_id  BIGINT NOT NULL REFERENCES document_type(id),
    label             VARCHAR(150) NOT NULL, -- libellé interne, pas montré au destinataire
    trigger_mode      VARCHAR(20) NOT NULL CHECK (trigger_mode IN ('business_rule', 'system_event')),
    from_email        VARCHAR(255),
    from_name         VARCHAR(150),
    reply_to_email    VARCHAR(255),
    is_active         BOOLEAN NOT NULL DEFAULT false,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by        BIGINT REFERENCES party_account(id),
    updated_by        BIGINT REFERENCES party_account(id)
);

CREATE UNIQUE INDEX uq_document_template_public_id ON document_template(public_id);
-- Invariant : une seule version active par type de document.
CREATE UNIQUE INDEX uq_document_template_one_active_per_type
    ON document_template(document_type_id) WHERE is_active = true;

CREATE TRIGGER trg_document_template_updated_at BEFORE UPDATE ON document_template
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMENT ON TABLE document_template IS 'Une ligne = une version de template. Plusieurs versions peuvent coexister (brouillon/test), UNE SEULE active à la fois par document_type (contrainte base, pas discipline applicative). Suppression = DELETE physique (pas de deleted_at) -- aucun document généré n''en dépend historiquement (décision actée : pas de versionnage des documents générés).';

CREATE TABLE document_template_translation (
    template_id    BIGINT NOT NULL REFERENCES document_template(id),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    subject        VARCHAR(255), -- NULL si output_kind='pdf' (pas de sujet pour un document non-email)
    content        TEXT NOT NULL, -- format neutre : {{code_composant}}, jamais de syntaxe de moteur (Twig/Jinja/Blade)
    PRIMARY KEY (template_id, language_code)
);

COMMENT ON TABLE document_template_translation IS 'Contenu par langue (EN pivot, FR, AR -- repli sur EN si traduction manquante, règle applicative). content = format neutre de substitution, portable indépendamment du stack (ADR implicite de cette session).';

-- ============================================================
-- document_trigger_rule : QUAND un template business_rule se
-- déclenche. Composable par le client (combinaisons de conditions déjà
-- connues, jamais de nouvelle dimension inventable). Pour
-- trigger_mode='system_event', aucune ligne ici -- déclenché
-- directement par le code appelant.
-- ============================================================
CREATE TABLE document_trigger_rule (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    template_id        BIGINT NOT NULL REFERENCES document_template(id),
    context_type_code  VARCHAR(30) NOT NULL REFERENCES document_context_type(code),
    label              VARCHAR(150), -- libellé libre interne (ex: "Envoi auto à la validation B2B")
    is_active          BOOLEAN NOT NULL DEFAULT true,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id)
);

CREATE INDEX idx_document_trigger_rule_template ON document_trigger_rule(template_id);
CREATE INDEX idx_document_trigger_rule_context ON document_trigger_rule(context_type_code);

-- ------------------------------------------------------------
-- Dimensions de condition -- contexte 'booking' uniquement (seul
-- construit aujourd'hui). Combinées en AND entre dimensions,
-- multi-select en OR à l'intérieur d'une dimension (vide = pas de
-- restriction sur cette dimension) -- même logique que Pricing.
-- ------------------------------------------------------------
CREATE TABLE document_trigger_action (
    code  VARCHAR(30) PRIMARY KEY -- 'creation','validation','annulation','manual'...
);
INSERT INTO document_trigger_action (code) VALUES ('creation'), ('validation'), ('annulation'), ('manual');

CREATE TABLE document_trigger_rule_action (
    rule_id      BIGINT NOT NULL REFERENCES document_trigger_rule(id),
    action_code  VARCHAR(30) NOT NULL REFERENCES document_trigger_action(code),
    PRIMARY KEY (rule_id, action_code)
);

CREATE TABLE document_trigger_rule_booking_status (
    rule_id      BIGINT NOT NULL REFERENCES document_trigger_rule(id),
    status_code  VARCHAR(30) NOT NULL REFERENCES booking_status(code),
    PRIMARY KEY (rule_id, status_code)
);

CREATE TABLE document_trigger_rule_channel (
    rule_id      BIGINT NOT NULL REFERENCES document_trigger_rule(id),
    channel_code VARCHAR(30) NOT NULL REFERENCES booking_channel(code),
    PRIMARY KEY (rule_id, channel_code)
);

-- ============================================================
-- document_recipient_role : rôles destinataires connus (client,
-- affilié, agence, fournisseur/hôtel, user connecté). Réutilise
-- party_role existant pour 'fournisseur' -- générique tous services,
-- confirmé en session (booking.supplier_account_id déjà transverse).
-- ============================================================
CREATE TABLE document_recipient_role (
    code  VARCHAR(30) PRIMARY KEY
);
INSERT INTO document_recipient_role (code) VALUES
    ('client'), ('affilie'), ('agence'), ('fournisseur'), ('user_connecte');

-- ============================================================
-- document_recipient_rule : destinataires d'un template. Une ligne
-- peut combiner un rôle dynamique (résolu à l'envoi) ET une adresse
-- statique (confirmé en session, pas de XOR ici contrairement à RBAC).
-- ============================================================
CREATE TABLE document_recipient_rule (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    template_id      BIGINT NOT NULL REFERENCES document_template(id),
    recipient_type   VARCHAR(5) NOT NULL CHECK (recipient_type IN ('to', 'cc', 'cci')),
    recipient_role_code VARCHAR(30) REFERENCES document_recipient_role(code), -- résolu dynamiquement à l'envoi
    static_email     VARCHAR(255), -- adresse fixe, identique pour toutes les réservations (ex: archive agence)

    CONSTRAINT chk_document_recipient_rule_not_empty
        CHECK (recipient_role_code IS NOT NULL OR static_email IS NOT NULL)
);

CREATE INDEX idx_document_recipient_rule_template ON document_recipient_rule(template_id, recipient_type);

COMMENT ON TABLE document_recipient_rule IS 'Destinataires par template et par type (to/cc/cci). Une ligne peut porter un rôle dynamique et/ou une adresse statique (pas de XOR, confirmé en session) -- au moins l''un des deux (chk_document_recipient_rule_not_empty).';

-- ============================================================
-- config_application_setting : singleton, paramètres d'installation
-- (ADR-004 : 1 serveur = 1 client, pas d'espace de clés nécessaire).
-- Colonnes explicites typées, PAS de clé/valeur générique (anti-EAV).
-- ============================================================
CREATE TABLE config_application_setting (
    id              SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    mfa_issuer_name VARCHAR(100), -- NULLABLE, sans défaut : jamais de nom client/produit en dur (audit défauts 24/07)
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO config_application_setting (id) VALUES (1);
