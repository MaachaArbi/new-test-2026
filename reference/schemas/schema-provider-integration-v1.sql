-- ============================================================================
-- Module         : Provider Integration (provider_) — API IN (fournisseurs
--                   de contenu) + Outils techniques (SMS/emailing/paiement)
-- Objet          : Connectivité API multi-fournisseurs — modèle "plugin"
--                   (manifeste de connexion détenu par OctaSoft Static Data,
--                   jamais recopié en base client), connexions concrètes
--                   avec credentials chiffrés, corrélation légère vers un
--                   store de logs externe (API gateway, hors périmètre DB).
-- Version        : 1.0
-- Date           : 21 juillet 2026
-- Périmètre      : API IN (fournisseurs de contenu Hôtel/Vol/Voiture/Train/
--                   Ferries/tout service Booking) + Outils techniques purs
--                   (SMS/Email/Passerelle de paiement).
-- Hors périmètre (reporté, voir sujets-reportes.md) : API OUT (devenir
--                   fournisseur pour un client tiers), Channel Manager,
--                   Contracting hôtelier avancé (tarifs d'achat, micro-
--                   marges de contrat), gestion des licences/entitlements
--                   (transverse à tout le projet, pas propre à ce module).
-- Dépend de      : content_provider (schema-ref-static-v1.sql), party_account
--                   + party_account_office (schema-party-account-v1.sql),
--                   log_entity_type + log_audit_trigger() (schema-log-v1.sql)
-- Réfs           : ADR-002 (logique métier hors DB), ADR-004 (isolation
--                   1 serveur = 1 client), ADR-006 (log_audit),
--                   sujets-reportes.md §1, §6/§33, §44, §49, §51, §52
-- ============================================================================

-- ============================================================================
-- PARTIE 1 — OUTILS TECHNIQUES PURS (SMS/Email/Passerelle de paiement)
-- Entité séparée de content_provider : jamais de oct_code, jamais de
-- synchronisation OctaSoft Static Data (décision de cadrage, tranchée le
-- 17/07 et confirmée le 20/07 -- deux catégories jamais confondues).
-- ============================================================================

-- ------------------------------------------------------------
-- technical_service_category : catégorie ouverte (email/sms/passerelle de
-- paiement...), volontairement en table de référence -- confirmé en
-- session (21/07) que cet ensemble PEUT s'ouvrir (ex: chat/WhatsApp, push
-- notification), cohérent avec le principe anti-ENUM du projet. Sert
-- aussi bien à choisir le bon formulaire d'admin qu'à filtrer la future
-- marketplace de plugins.
-- ------------------------------------------------------------
CREATE TABLE technical_service_category (
    code        VARCHAR(30) PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE technical_service_category IS 'Catégorie de prestataire technique pur (email/sms/passerelle de paiement...). Table de référence extensible -- confirmé en session (21/07) que le besoin peut s''élargir, contrairement à is_active (fermé à dessein).';

INSERT INTO technical_service_category (code, label, sort_order) VALUES
    ('email',           'Emailing',              0),
    ('sms',             'SMS',                   1),
    ('payment_gateway',  'Passerelle de paiement', 2);

-- ------------------------------------------------------------
-- technical_service_provider : la techhouse technique elle-même (Brevo,
-- clictopay...). Portée volontairement minimale, même logique que
-- content_provider (entité fondatrice, étendue par provider_connection,
-- jamais par elle-même) -- confirmé en session : id/code/name/catégorie
-- suffisent, rien d'autre à porter ici (les paramètres variables vivent
-- dans provider_connection.config, le manifeste du plugin les décrit).
-- ------------------------------------------------------------
CREATE TABLE technical_service_provider (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id      UUID NOT NULL DEFAULT gen_random_uuid(),
    code           VARCHAR(50) NOT NULL,
    name           VARCHAR(255) NOT NULL,
    category_code  VARCHAR(30) NOT NULL REFERENCES technical_service_category(code),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE technical_service_provider IS 'Prestataire technique pur (Brevo, clictopay...) -- jamais de oct_code, jamais synchronisé avec OctaSoft Static Data (distinct de content_provider, décision 17/07 confirmée 20/07). Entité fondatrice pour ce module, étendue par provider_connection.';

CREATE UNIQUE INDEX uq_technical_service_provider_public_id ON technical_service_provider(public_id);
CREATE UNIQUE INDEX uq_technical_service_provider_code ON technical_service_provider(code);

CREATE TRIGGER trg_technical_service_provider_updated_at BEFORE UPDATE ON technical_service_provider
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================================
-- PARTIE 2 — CONNEXION UNIFIÉE (modèle "plugin")
-- Table unique pour les deux catégories (API IN contenu + outils
-- techniques), verrouillée par CHECK croisés -- même pattern déjà validé
-- en sandbox sur Pricing (rule_nature_code + FK composite, sujets-
-- reportes.md §53). Le CONTRAT de connexion (quels champs credentials,
-- quelles clés config avec type/défaut/aide) vit dans le manifeste du
-- plugin -- JAMAIS recopié en structure ici (décision centrale de
-- session, 21/07) : provider_connection ne stocke QUE les valeurs
-- concrètes saisies par le client, jamais la déclaration du contrat.
-- ============================================================================

-- ------------------------------------------------------------
-- provider_connection_deactivation_reason : petit référentiel ouvert --
-- pourquoi une connexion a été désactivée (manuel vs automatique). Décidé
-- en session (21/07) en complément du trigger d'audit générique : lecture
-- directe sur la ligne, sans devoir ouvrir l'historique -- log_audit
-- (posé sur cette table) reste la source de l'historique complet des
-- bascules successives.
-- ------------------------------------------------------------
CREATE TABLE provider_connection_deactivation_reason (
    code        VARCHAR(30) PRIMARY KEY,
    label       VARCHAR(150) NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO provider_connection_deactivation_reason (code, label, sort_order) VALUES
    ('manual',              'Désactivation manuelle',                      0),
    ('auto_slow_response',   'Désactivation automatique -- temps de réponse élevé', 1),
    ('auto_error_rate',        'Désactivation automatique -- taux d''erreur élevé',    2);

-- ------------------------------------------------------------
-- provider_connection : table unique, deux ancres mutuellement exclusives.
--
-- Ancre CONTENU (content_provider_id renseigné) : un fournisseur avec qui
-- l'agence a une dette réelle -- fournisseur_account_id (party_account,
-- rôle fournisseur) devient alors OBLIGATOIRE (confirmé en session :
-- "Provider" = la techhouse/classe de code à appeler, "Fournisseur" =
-- l'entité économique à qui on doit de l'argent -- deux FK distinctes et
-- légitimes, pas un doublon comme lu initialement sur l'écran legacy).
--
-- Ancre TECHNIQUE (technical_service_provider_id renseigné) :
-- fournisseur_account_id INTERDIT -- confirmé en session, aucun
-- prestataire technique pur n'a de party_account fournisseur derrière
-- (paiement par abonnement, hors Règlements fournisseur classique).
--
-- Grain de la ligne : AUCUNE combinaison de FK n'est structurellement
-- unique (confirmé en session -- deux connexions clictopay identiques en
-- (provider, fournisseur, bureau) peuvent coexister, ex: deux comptes
-- clictopay distincts). Chaque ligne se distingue par son label libre,
-- saisi par l'utilisateur -- pas de contrainte d'unicité en base. La
-- désambiguïsation "laquelle utiliser en cas de doublon actif" reste
-- ENTIÈREMENT côté Application, jamais résolue structurellement ici
-- (décision explicite de session, 21/07).
--
-- office_account_id NULLABLE : une connexion peut être globale (partagée
-- par toute l'installation, cas par défaut d'un client à bureau unique)
-- ou spécifique à un party_account_office -- confirmé : jamais au niveau
-- sales_point (simple lieu, pas d'identité économique), jamais au niveau
-- franchise (party_account_franchise) -- uniquement party_account_office.
-- ------------------------------------------------------------
CREATE TABLE provider_connection (
    id                          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                  UUID NOT NULL DEFAULT gen_random_uuid(),

    content_provider_id           BIGINT REFERENCES content_provider(id),
    technical_service_provider_id   BIGINT REFERENCES technical_service_provider(id),

    fournisseur_account_id            BIGINT REFERENCES party_account(id), -- OBLIGATOIRE si ancre contenu, INTERDIT si ancre technique (CHECK)
    office_account_id                   BIGINT REFERENCES party_account_office(account_id), -- NULL = connexion globale

    label                                  VARCHAR(200) NOT NULL, -- libellé libre, seul élément distinctif entre connexions "jumelles"

    -- Contrat de connexion NON stocké ici (vit dans le manifeste du
    -- plugin, monde "1a" -- version_code suffit à retrouver le bon
    -- contrat, pas besoin de le recopier -- décision de session 21/07).
    credentials                              JSONB NOT NULL DEFAULT '{}'::jsonb, -- valeurs saisies par le client (login/pwd, token...) -- CHIFFRÉ CÔTÉ APPLICATION avant écriture, jamais en clair sur disque (décision de session -- option 1, cohérent ADR-002)
    config                                     JSONB NOT NULL DEFAULT '{}'::jsonb, -- UNIQUEMENT les overrides -- les valeurs non modifiées héritent du défaut déclaré par le manifeste, jamais recopiées
    version_code                                 VARCHAR(30) NOT NULL, -- version du manifeste du plugin contre laquelle credentials/config ont été validés (monde 1a -- pas de snapshot du manifeste lui-même)

    is_active                                       BOOLEAN NOT NULL DEFAULT true, -- exception assumée à la règle anti-ENUM du projet -- confirmé en session : ensemble fermé à 2 valeurs, aucun 3e état voulu (contrairement à technical_service_category)
    deactivated_reason_code                            VARCHAR(30) REFERENCES provider_connection_deactivation_reason(code), -- NULL si is_active=true (CHECK)
    note                                                  TEXT, -- contexte libre, ex: "désactivé le 21/07 suite 15 timeouts consécutifs > 10s"

    created_at                                              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                                                TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                                                  BIGINT REFERENCES party_account(id),
    updated_by                                                    BIGINT REFERENCES party_account(id),

    -- Exactement une des deux ancres, jamais les deux, jamais aucune.
    CONSTRAINT chk_provider_connection_single_anchor CHECK (
        (content_provider_id IS NOT NULL AND technical_service_provider_id IS NULL)
        OR
        (content_provider_id IS NULL AND technical_service_provider_id IS NOT NULL)
    ),

    -- Fournisseur obligatoire si contenu, interdit si technique pur.
    CONSTRAINT chk_provider_connection_fournisseur CHECK (
        (content_provider_id IS NOT NULL AND fournisseur_account_id IS NOT NULL)
        OR
        (technical_service_provider_id IS NOT NULL AND fournisseur_account_id IS NULL)
    ),

    -- Une raison de désactivation n'a de sens que sur une connexion inactive.
    CONSTRAINT chk_provider_connection_deactivation_reason CHECK (
        (is_active = true AND deactivated_reason_code IS NULL)
        OR
        (is_active = false)
    )
);

COMMENT ON TABLE provider_connection IS 'Connexion concrète à un provider (contenu ou technique pur), modèle "plugin" -- le CONTRAT (champs credentials/config attendus) vit dans le manifeste du plugin, jamais recopié ici. Aucune combinaison de FK n''est unique (confirmé en session 21/07) : le label libre est le seul élément distinctif, la désambiguïsation entre connexions actives concurrentes reste entièrement côté Application.';

CREATE UNIQUE INDEX uq_provider_connection_public_id ON provider_connection(public_id);
CREATE INDEX idx_provider_connection_content_provider ON provider_connection(content_provider_id) WHERE content_provider_id IS NOT NULL;
CREATE INDEX idx_provider_connection_technical_provider ON provider_connection(technical_service_provider_id) WHERE technical_service_provider_id IS NOT NULL;
CREATE INDEX idx_provider_connection_fournisseur ON provider_connection(fournisseur_account_id) WHERE fournisseur_account_id IS NOT NULL;
CREATE INDEX idx_provider_connection_office ON provider_connection(office_account_id) WHERE office_account_id IS NOT NULL;
CREATE INDEX idx_provider_connection_active ON provider_connection(is_active);

CREATE TRIGGER trg_provider_connection_updated_at BEFORE UPDATE ON provider_connection
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Historique complet des bascules (dont désactivation auto) -- gratuit,
-- aucune nouvelle table/colonne : réutilise log_audit déjà figé (module
-- log_). Décision de session (21/07) : colonnes de lecture directe +
-- trigger d'audit, les deux se complètent, pas concurrents.
INSERT INTO log_entity_type (code, label, activity_retention_days, audit_retention_days) VALUES
    ('provider_connection', 'Connexion provider', NULL, 365);

CREATE TRIGGER trg_provider_connection_audit AFTER INSERT OR UPDATE OR DELETE ON provider_connection
    FOR EACH ROW EXECUTE FUNCTION log_audit_trigger('provider_connection');

-- ============================================================================
-- PARTIE 3 — CORRÉLATION D'APPELS (provider_call_log)
-- Store des LOGS COMPLETS (request/response, fichiers) explicitement HORS
-- de cette base (sujets-reportes.md §44, confirmé et durci en session
-- 21/07) -- vivra sur une API gateway/microservice séparé (infra, hors
-- périmètre de ce Project). Ici : SEULEMENT un pointeur léger, minimal,
-- partitionné pour absorber le volume (jusqu'à 10M appels/jour sur le
-- plus gros client) -- correlation_id permet de retrouver le payload
-- complet côté gateway. Dépendance à la disponibilité de la gateway pour
-- consulter les logs détaillés est ACCEPTÉE (confirmé en session).
-- ============================================================================

-- ------------------------------------------------------------
-- provider_call_status : Success/Erreur/Warning -- confirmé OUVERT
-- (contrairement à provider_connection.is_active), donc table de
-- référence plutôt que CHECK fermé.
-- ------------------------------------------------------------
CREATE TABLE provider_call_status (
    code        VARCHAR(40) PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO provider_call_status (code, label, sort_order) VALUES
    ('success', 'Succès',  0),
    ('warning', 'Alerte',  1), -- ex: réponse 200 mais lente (>5s), à surveiller sans être un échec
    ('error',   'Échec',   2); -- ex: HTTP 500, ou 200 avec message métier d'erreur (ex: solde insuffisant)

-- ------------------------------------------------------------
-- provider_call_error_code : vocabulaire NORMALISÉ PAR L'AGENCE elle-même
-- (confirmé en session : "je convertis vers mes propres codes"), jamais
-- le code brut hétérogène renvoyé par chaque provider tiers -- permet des
-- statistiques de taux d'erreur comparables entre providers.
-- ------------------------------------------------------------
CREATE TABLE provider_call_error_code (
    code        VARCHAR(50) PRIMARY KEY,
    label       VARCHAR(200) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE provider_call_error_code IS 'Vocabulaire d''erreur NORMALISÉ par l''agence (mapping fait côté Domain depuis l''erreur brute du provider) -- jamais le code brut hétérogène du tiers. Table extensible, alimentée au fil de l''eau.';

-- ------------------------------------------------------------
-- provider_call_log : pointeur léger de corrélation, PAS de payload.
-- Volume attendu : jusqu'à ~10M lignes/jour (recherches) pour ~1000
-- réservations/jour chez le plus gros client -- partitionné mensuellement
-- comme booking/core_session/core_auth_attempt. purge_at est calculé et
-- éventuellement RECALCULÉ par l'Application selon une politique PROPRE
-- À CHAQUE service/endpoint (ex: recherche non aboutie -> J+1, email
-- d'inscription -> J+2, email de confirmation -> date d'arrivée) --
-- confirmé en session : pas de règle générale calculable en base, la
-- politique de rétention vit entièrement côté Application, par ligne.
-- ------------------------------------------------------------
CREATE TABLE provider_call_log (
    id                     BIGINT GENERATED BY DEFAULT AS IDENTITY, -- BY DEFAULT (pas ALWAYS) : PK composite (id, created_at) sur table partitionnée -- même contournement Doctrine que booking, amendement ADR-018 du 21/07/2026.
    public_id              UUID NOT NULL DEFAULT gen_random_uuid(),
    correlation_id           UUID NOT NULL, -- clé de récupération du payload complet côté gateway (hors DB)

    provider_connection_id     BIGINT NOT NULL REFERENCES provider_connection(id),

    -- FK applicative, même principe que log_activity/log_audit -- NULL
    -- pour les appels sans entité rattachée (recherche stérile jamais
    -- convertie, appel Brevo/clictopay hors contexte résa). Réutilise le
    -- référentiel log_entity_type déjà figé -- pas de nouveau référentiel.
    entity_type                  VARCHAR(30) REFERENCES log_entity_type(code),
    entity_id                      BIGINT,

    service_name                     VARCHAR(100) NOT NULL, -- ex: 'HotelQuote','BookingCreation','SendConfirmationEmail' -- texte libre, propre à chaque provider/endpoint

    status_code                        VARCHAR(20) NOT NULL REFERENCES provider_call_status(code),
    error_code                           VARCHAR(50) REFERENCES provider_call_error_code(code), -- NULL si status='success'
    message                                TEXT, -- message libre, lisible humain (ex: "Solde insuffisant")

    timing_ms                                INT, -- durée de l'appel en millisecondes -- NULL si jamais complété (échec avant mesure)

    purge_at                                   TIMESTAMPTZ NOT NULL, -- calculé (et potentiellement recalculé) par l'Application selon la politique de rétention propre à service_name
    created_at                                   TIMESTAMPTZ NOT NULL DEFAULT now(), -- clé de partition

    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (created_at);

COMMENT ON TABLE provider_call_log IS 'Pointeur léger de corrélation vers le payload complet request/response, stocké HORS de cette base (API gateway/microservice séparé, sujets-reportes.md §44). AUCUN payload ici -- table volontairement minimale pour absorber un volume élevé (jusqu''à ~10M lignes/jour). purge_at porté PAR LIGNE, politique de rétention propre à chaque service/endpoint, entièrement pilotée par l''Application (pas de règle générale calculable en base).';

-- Unicité de public_id sur table partitionnée : doit inclure la clé de
-- partition (même contrainte technique que uq_booking_public_id).
CREATE UNIQUE INDEX uq_provider_call_log_public_id ON provider_call_log(public_id, created_at);

CREATE INDEX idx_provider_call_log_correlation ON provider_call_log(correlation_id);
CREATE INDEX idx_provider_call_log_connection ON provider_call_log(provider_connection_id, created_at DESC);
CREATE INDEX idx_provider_call_log_entity ON provider_call_log(entity_type, entity_id, created_at DESC) WHERE entity_id IS NOT NULL;
CREATE INDEX idx_provider_call_log_status ON provider_call_log(status_code, created_at DESC);
CREATE INDEX idx_provider_call_log_purge ON provider_call_log(purge_at);

-- Partitions mensuelles explicites (même pattern que booking) --
-- couvrant le mois courant + 2 mois à venir + DEFAULT pour tout le reste
-- (rattrapage). Le job de purge (hors DB, cohérent ADR-002) devra à la
-- fois supprimer les lignes où purge_at <= now() ET DROP les partitions
-- mensuelles anciennes devenues entièrement vides -- ne jamais se
-- contenter de les vider ligne par ligne à ce volume (confirmé en
-- session, point technique soulevé par le chat pilote).
CREATE TABLE provider_call_log_y2026m07 PARTITION OF provider_call_log FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');
CREATE TABLE provider_call_log_y2026m08 PARTITION OF provider_call_log FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');
CREATE TABLE provider_call_log_y2026m09 PARTITION OF provider_call_log FOR VALUES FROM ('2026-09-01') TO ('2026-10-01');
CREATE TABLE provider_call_log_default   PARTITION OF provider_call_log DEFAULT;

-- ============================================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================================
-- 1. CONTRAT DE CONNEXION (credentials/config) : la structure exacte
--    attendue (quels champs, quels types, quelles valeurs par défaut,
--    quelle aide contextuelle) N'EST PAS modélisée en base -- elle vit
--    dans le manifeste du plugin, livré et versionné par OctaSoft
--    (marketplace décrite en session, hors périmètre de ce Project). La
--    couche Domain valide credentials/config à l'écriture contre le
--    manifeste de version_code. Toute évolution du contrat (nouveau
--    champ, renommage) se fait en publiant une nouvelle version du
--    plugin -- AUCUNE migration de schéma côté base client.
-- 2. CHIFFREMENT : credentials est chiffré CÔTÉ APPLICATION avant
--    écriture (option 1 retenue en session, cohérent ADR-002 -- la base
--    ne porte aucune logique cryptographique, contrairement à
--    l'alternative pgcrypto qui aurait mis de la logique en base). La
--    colonne reste un JSONB standard, sans spécificité de schéma pour
--    cette raison.
-- 3. AUCUNE UNICITÉ STRUCTURELLE sur (content_provider_id/
--    technical_service_provider_id, fournisseur_account_id,
--    office_account_id) -- confirmé explicitement en session (21/07) :
--    deux connexions "jumelles" peuvent coexister (ex: deux comptes
--    clictopay distincts). La désambiguïsation "laquelle utiliser" en
--    cas de doublons actifs reste ENTIÈREMENT côté Application, non
--    résolue en base -- décision assumée, pas un oubli.
-- 4. is_active en BOOLEAN, PAS une table de référence -- exception
--    documentée au principe anti-ENUM du projet (confirmé en session :
--    ensemble fermé à 2 valeurs, "juste actif/inactif, c'est tout").
--    Contraste volontaire avec technical_service_category/
--    provider_call_status, tous deux confirmés ouverts et donc en table
--    de référence.
-- 5. provider_call_log NE CONTIENT AUCUN PAYLOAD -- le store complet
--    (request/response, fichiers téléchargeables) vit sur une API
--    gateway/microservice séparé, non conçu dans ce Project (durcit
--    sujets-reportes.md §44). MyGo interroge cette gateway via
--    correlation_id pour tout affichage détaillé -- dépendance à sa
--    disponibilité ACCEPTÉE (confirmé en session).
-- 6. RÉTENTION PAR LIGNE, PAS PAR TYPE : provider_call_log.purge_at
--    diffère de log_entity_type.retention_days (rétention par type,
--    uniforme) -- ici la politique est propre à chaque service_name et
--    calculée par l'Application au moment de l'écriture, potentiellement
--    RECALCULÉE ensuite (ex: résa reportée -> date d'arrivée décalée).
--    Aucune contrainte d'immutabilité sur purge_at.
-- 7. HORS PÉRIMÈTRE DE CETTE SESSION (reportés, voir sujets-reportes.md) :
--    API OUT (devenir fournisseur pour un client tiers -- nature exacte
--    du "customer" côté Party non tranchée), Channel Manager, gestion des
--    licences/entitlements (transverse à tout le projet, pas propre à ce
--    module -- même un bouton dans une page pourrait un jour être
--    concerné). Contracting hôtelier avancé reste également hors
--    périmètre (autre moitié du dernier module, session dédiée à venir).
-- 8. content_provider ET booking_provider_snapshot restent INCHANGÉS --
--    aucune réouverture de ref_static ni de Booking dans cette session.
--    booking_provider_snapshot garde son rôle actuel (payload brut
--    rattaché à une booking), distinct et non fusionné avec
--    provider_call_log (qui, lui, couvre TOUS les appels, liés ou non à
--    une réservation, y compris Brevo/clictopay).
-- ============================================================================
