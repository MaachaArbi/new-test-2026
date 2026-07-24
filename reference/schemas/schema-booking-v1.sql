-- ============================================================
-- Module         : Booking (booking_)
-- Objet          : Réservations multi-services (hôtel, vol, transfert,
--                   excursion, visa, spa, location voiture...). Une
--                   réservation = 1 service = 1 fournisseur, jamais un
--                   service composé (un "voyage organisé" est un simple
--                   regroupement de réservations dans un même dossier).
--                   Brique consommatrice de party_ (tiers, bureaux) et
--                   ref_ (langues, devises).
-- Version        : 1.2 - Réouverture ciblée du 20/07/2026 : renommage
--                   booking_hotel_detail -> booking_accommodation_detail
--                   (+ FK réelle ref_accommodation.id), processing_status_code
--                   (+log_activity), booking_payment.collected_by_account_id,
--                   correction définitions booking_channel.api_in/api_out,
--                   ajout service 'guide', FK sales_point_id/sales_point_payment_id.
-- Date           : 2026-07-20
-- Réfs           : ADR-002 (logique métier hors DB, jamais de trigger
--                   métier), ADR-005 (Politique de disparition — quatre
--                   régimes), ADR-010 (PostgreSQL 16), ADR-016
--                   (partitionnement par date dès le début), ADR-017
--                   (pattern interface/stub pour dépendances vers modules
--                   pas encore implémentés), ADR-018 (BIGINT identity +
--                   public_id)
-- Dépend de      : party_account, party_account_office (module party_,
--                   voir schema-party-account-v1.sql) — À EXÉCUTER AVANT
--                   ref_currency (module ref_, voir schema-ref-common.sql)
--                   — À EXÉCUTER AVANT
--                   ref_accommodation (module ref_static, schema-ref-static-v1.sql)
--                   — À EXÉCUTER AVANT, nouveau depuis le 20/07 (FK réelle
--                   sur booking_accommodation_detail)
--                   sales_point (module sales_point_, schema-sales_point-v1.sql)
--                   — À EXÉCUTER AVANT, nouveau depuis le 20/07 (FK sur booking)
--                   log_entity_type (module log_, schema-log-v1.sql) — pas
--                   de FK SQL réelle (entity_id applicatif), mais l'entrée
--                   'booking' doit exister pour que log_activity fonctionne
--                   set_updated_at() — définie dans schema-party-account-v1.sql,
--                   réutilisée ici (à recréer si ce module est déployé
--                   indépendamment, cf. note dans schema-core-identity-v1.sql)
-- ============================================================
-- Tables legacy remplacées : ost_sht_reservation, ost_sht_reservation_chambre,
-- ost_sht_reservation_chambre_jour, ost_sht_reservation_jour,
-- ost_sht_reservation_ligne, ost_sht_reservation_personne,
-- ost_sht_reservation_conditionannulation,
-- ost_sht_reservation_conditionannul_chambre
-- Hors périmètre (voir sujets-reportes.md, section Booking à créer) :
-- booking_charge (lignes de tarification détaillées, si besoin de
-- reporting transverse avéré un jour), sous-groupe "package" explicite,
-- lettrage paiement<->facture, calcul réel des échéances/soldes
-- (futur Cash Management), point de vente sur la résa.
-- ============================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;  -- gen_random_uuid()

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_service_type
-- Ajouter un nouveau service (ex: demande de visa) = une ligne ici,
-- pas une nouvelle table/menu/contrôleur. Extension spécifique
-- optionnelle possible via une table booking_<type>_detail (voir plus
-- bas, exemple booking_accommodation_detail).
-- ============================================================
CREATE TABLE booking_service_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   BOOLEAN NOT NULL DEFAULT true,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_service_type IS 'Référentiel des types de service réservables. Un service composé (voyage organisé) n''est jamais un type ici : c''est un regroupement de plusieurs booking dans un même booking_folder.';

INSERT INTO booking_service_type (code, sort_order) VALUES
    ('hotel',       0),
    ('flight',      1),
    ('transfer',    2),
    ('bus',         3), -- billet de ligne (car/bus), distinct de transfer (trajet privé point-à-point)
    ('excursion',   4),
    ('car_rental',  5),
    ('spa',         6),
    ('pool_access', 7),
    ('visa',        8),
    ('maritime',    9),
    ('train',       10),
    ('guide',       11), -- prestation facturée simple, aucune fiche Catalogue (confirmé Product/Catalogue, sujets-reportes.md §40)
    ('other',       99);
    -- city_tax retiré (15/07, 2e revirement) : rejoint booking_charge
    -- comme timbre/frais_dossier -- même raisonnement : pas de
    -- fournisseur/cycle de vie propre, toujours couplée au même séjour
    -- hôtelier. Contrairement à un vol/transfert/excursion, qui restent
    -- des booking séparés (fournisseur et temporalité indépendants).

CREATE TABLE booking_service_type_translation (
    service_type_code  VARCHAR(30) NOT NULL REFERENCES booking_service_type(code),
    language_code       VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label                VARCHAR(100) NOT NULL,
    PRIMARY KEY (service_type_code, language_code)
);

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_service_extension
-- Catalogue des "extensions" structurelles applicables à un
-- booking (tables filles 1-1 / 1-N : accommodation_detail,
-- transport_segment, car_rental_detail...). Découplé du code PHP :
-- autoriser un service_type à porter une extension = une ligne
-- dans booking_service_type_extension, sans redeploy.
-- (Volet A — conception BDD data-driven, 22/07.)
-- ============================================================
CREATE TABLE booking_service_extension (
    code        VARCHAR(30) PRIMARY KEY,
    label       VARCHAR(100) NOT NULL, -- libellé technique (anglais), pas destiné à l'UI
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_service_extension IS 'Référentiel des extensions structurelles de booking (accommodation, transport_segment, car_rental...). Source de vérité pour AssertBookingServiceType — pas de liste PHP figée.';

INSERT INTO booking_service_extension (code, label, sort_order) VALUES
    ('accommodation',     'Accommodation detail / hotel rooms', 0),
    ('transport_segment', 'Transport segments',                 1),
    ('car_rental',        'Car rental detail',                  2);

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_service_type_extension (N-N)
-- Quel(s) service_type peu(ven)t porter quelle extension.
-- Seed initial :
--   accommodation     <- hotel
--   transport_segment <- flight, train, maritime, transfer
--   car_rental        <- car_rental
-- ============================================================
CREATE TABLE booking_service_type_extension (
    service_type_code  VARCHAR(30) NOT NULL REFERENCES booking_service_type(code),
    extension_code     VARCHAR(30) NOT NULL REFERENCES booking_service_extension(code),
    PRIMARY KEY (service_type_code, extension_code)
);

COMMENT ON TABLE booking_service_type_extension IS 'Mapping N-N service_type ↔ extension. Ajouter une ligne (ex: bus → transport_segment) active le comportement sans toucher au code PHP.';

INSERT INTO booking_service_type_extension (service_type_code, extension_code) VALUES
    ('hotel',      'accommodation'),
    ('flight',     'transport_segment'),
    ('train',      'transport_segment'),
    ('maritime',   'transport_segment'),
    ('transfer',   'transport_segment'),
    ('car_rental', 'car_rental');

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_channel
-- Remplace frontOffice + front_office_xml (2 booléens qui se
-- chevauchaient partiellement dans le legacy) par un référentiel
-- unique et extensible.
-- ============================================================
CREATE TABLE booking_channel (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO booking_channel (code, sort_order) VALUES
    ('backoffice', 0), -- créée par un agent, back-office
    ('web',        1), -- site web / app public
    ('api_in',     2), -- CORRIGÉ 20/07 (sujets-reportes.md §49) : nous consommons l'API d'un tiers (ex: nous interrogeons/réservons chez un fournisseur via son API)
    ('api_out',    3); -- CORRIGÉ 20/07 : nous exposons notre API à un partenaire (ex: un partenaire B2B/channel manager réserve notre inventaire via notre API)

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_status
-- service_type_code NULL = statut générique (applicable à tout type de
-- service). Un statut peut aussi être spécifique à un service
-- (sous-états métier propres à l'hôtel par exemple).
-- ============================================================
CREATE TABLE booking_status (
    code                VARCHAR(30) PRIMARY KEY,
    service_type_code  VARCHAR(30) REFERENCES booking_service_type(code), -- NULL = générique, tous services
    is_final             BOOLEAN NOT NULL DEFAULT false, -- true = plus aucune transition possible (cancelled, completed)
    sort_order            SMALLINT NOT NULL DEFAULT 0,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_status IS 'Statuts internes de réservation, scopés par service_type quand pertinent (NULL = générique). Distinct du statut fournisseur brut, non structurant, stocké tel quel sur booking.supplier_status_label.';

INSERT INTO booking_status (code, service_type_code, is_final, sort_order) VALUES
    ('draft',       NULL, false, 0),
    ('on_option',   NULL, false, 1), -- BOOK_NOW_PAY_LATER : voucher émis, en attente de paiement avant option_expiry_at
    ('confirmed',   NULL, false, 2),
    ('completed',   NULL, true,  3),
    ('cancelled',   NULL, true,  4),
    ('no_show',     NULL, true,  5);

CREATE TABLE booking_status_translation (
    status_code    VARCHAR(30) NOT NULL REFERENCES booking_status(code),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(100) NOT NULL,
    PRIMARY KEY (status_code, language_code)
);

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_processing_status
-- État de SUIVI DE TRAITEMENT par l'équipe (ex: "En attente du client",
-- "Client contacté sérieux", "Attente confirmation disponibilité"...) --
-- ORTHOGONAL à booking_status (statut métier de la réservation :
-- draft/confirmed/cancelled...). Un booking peut être status_code='draft'
-- ET processing_status_code='client_contacted' simultanément, même
-- principe déjà établi pour is_on_request vs status_code. Décision de
-- session (20/07, sujets-reportes.md §35) : dénormalisée sur booking
-- pour filtrage/affichage rapide (option c) + chaque changement loggé
-- dans log_activity (module log_, entity_type='booking',
-- event_type='processing_status_change') pour l'historique complet --
-- même pattern que booking.status_code/booking_log historique (déjà
-- résolu via log_activity.status_code_snapshot, voir modele-conceptuel-log.md).
-- ============================================================
CREATE TABLE booking_processing_status (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_processing_status IS 'Référentiel des états de suivi de traitement (équipe), orthogonal à booking_status. Table, pas ENUM -- même principe que le reste des référentiels Booking.';

INSERT INTO booking_processing_status (code, sort_order) VALUES
    ('pending_client_response',   0), -- En attente du client
    ('client_contacted_serious',  1), -- Client contacté sérieux
    ('pending_availability', 2); -- Attente confirmation disponibilité
-- Liste volontairement minimale (3 exemples de sujets-reportes.md §35) --
-- table extensible sans migration, à enrichir au fil de l'eau par l'équipe.

CREATE TABLE booking_processing_status_translation (
    processing_status_code  VARCHAR(30) NOT NULL REFERENCES booking_processing_status(code),
    language_code            VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label                    VARCHAR(100) NOT NULL,
    PRIMARY KEY (processing_status_code, language_code)
);

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_on_request_reason
-- Pourquoi une réservation est "sur demande" (booking.is_on_request=true).
-- approval_type distingue QUI doit agir : 'supplier' (le fournisseur/
-- hôtel doit confirmer) vs 'internal' (un responsable de l'agence doit
-- approuver, ex: solde B2B insuffisant). Table de référence (pas un
-- ENUM figé) pour ajouter une raison sans migration -- même principe
-- que party_role/booking_service_type.
-- ============================================================
CREATE TABLE booking_on_request_reason (
    code           VARCHAR(30) PRIMARY KEY,
    approval_type  VARCHAR(20) NOT NULL CHECK (approval_type IN ('supplier', 'internal')),
    sort_order     SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_on_request_reason IS 'Raison de mise "sur demande". approval_type pilote le workflow déclenché par l''Application (notifier le fournisseur vs soumettre à approbation interne) -- consommé par booking_approval.approval_type.';

INSERT INTO booking_on_request_reason (code, approval_type, sort_order) VALUES
    ('insufficient_stock',     'supplier', 0),
    ('retrocession_delay',     'supplier', 1),
    ('min_stay_not_met',       'supplier', 2),
    ('stop_sales',             'supplier', 3),
    ('insufficient_balance',   'internal', 4),
    ('room_on_request',        'supplier', 5), -- raison générique, non détaillée (confirmé sur donnée réelle : "Chambre sur demande" sans motif précis)
    ('account_policy',         'internal', 6); -- politique commerciale du compte (force_on_request, balayage Party 24/07)

CREATE TABLE booking_on_request_reason_translation (
    reason_code    VARCHAR(30) NOT NULL REFERENCES booking_on_request_reason(code),
    language_code  VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label          VARCHAR(100) NOT NULL,
    PRIMARY KEY (reason_code, language_code)
);

-- Traductions de account_policy (les 6 raisons antérieures n'avaient pas
-- de seed de traduction dans ce fichier — écart historique non corrigé ici).
INSERT INTO booking_on_request_reason_translation (reason_code, language_code, label) VALUES
    ('account_policy', 'en', 'Account commercial policy'),
    ('account_policy', 'fr', 'Politique commerciale du compte'),
    ('account_policy', 'ar', 'سياسة الحساب التجارية');

-- ============================================================
-- booking_on_request_flag : raison(s) de mise "sur demande"
-- (booking.is_on_request=true). Table 1-N, PAS une colonne unique sur
-- booking -- confirmé sur données réelles : une réservation peut
-- cumuler plusieurs raisons simultanément (ex: stock ET solde
-- insuffisants en même temps). Chaque ligne peut porter un texte libre
-- explicatif en plus du code (ex: "solde = 0.000 DT").
-- ============================================================
CREATE TABLE booking_on_request_flag (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id   BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    reason_code  VARCHAR(30) NOT NULL REFERENCES booking_on_request_reason(code),
    detail       TEXT, -- texte libre explicatif, optionnel (ex: "Chambre single, Chambre single")
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_on_request_flag IS 'Raisons de mise sur demande, 1-N (remplace booking.on_request_reason_code, colonne unique invalidée par des cas réels à raisons multiples simultanées).';

CREATE UNIQUE INDEX uq_booking_on_request_flag ON booking_on_request_flag(booking_id, reason_code);
CREATE INDEX idx_booking_on_request_flag_booking ON booking_on_request_flag(booking_id);

-- ============================================================
-- booking_folder : dossier, regroupe une ou plusieurs réservations.
-- Porte le client "principal" (hérité par toutes les réservations du
-- dossier, cf. décision : un dossier = un client, le partage entre
-- plusieurs clients se gère au niveau de la réservation via
-- booking_payer_split, pas ici).
-- ============================================================
CREATE TABLE booking_folder (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),

    reference_code     VARCHAR(30) NOT NULL, -- numéro dossier lisible, exposé client/agent
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id), -- client principal, hérité par tous les booking du dossier
    office_account_id  BIGINT NOT NULL REFERENCES party_account(id), -- bureau exploitant (doit porter party_account_office, règle applicative)

    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at         TIMESTAMPTZ,
    created_by         BIGINT REFERENCES party_account(id),
    updated_by         BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_folder IS 'Dossier de réservations. Un dossier = un client porteur (party_account_id) ; le cas rare de partage entre plusieurs clients se gère par réservation via booking_payer_split, pas ici. Un "voyage organisé" est simplement un dossier avec plusieurs booking, sans entité package dédiée (aucun cas concret ne l''exige à ce jour).';

CREATE UNIQUE INDEX uq_booking_folder_public_id ON booking_folder(public_id);
CREATE UNIQUE INDEX uq_booking_folder_reference_code ON booking_folder(reference_code);
CREATE INDEX idx_booking_folder_account ON booking_folder(party_account_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_booking_folder_office ON booking_folder(office_account_id) WHERE deleted_at IS NULL;

CREATE TRIGGER trg_booking_folder_updated_at BEFORE UPDATE ON booking_folder
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- booking : PIVOT, 1 ligne = 1 service = 1 fournisseur. Porte les
-- champs communs à tout type de service (~80%, cf. discussion) :
-- dates, montants, devises, statut. Le spécifique par service vit
-- dans des tables d'extension 1-1 optionnelles (booking_accommodation_detail...).
--
-- PARTITIONNEMENT (ADR-016) : par booking_date (date de création de la
-- réservation, PAS la date de séjour/départ — cf. start_date/end_date
-- ci-dessous), en RANGE mensuel. PostgreSQL exige que la clé de
-- partition fasse partie de toute clé primaire/unique -> PK composite
-- (id, booking_date). CONSÉQUENCE ASSUMÉE : les tables filles ne
-- déclarent PAS de FK SQL formelle vers booking (qui nécessiterait de
-- porter booking_date sur chacune) mais une FK applicative sur
-- booking_id seul, avec index btree simple -- même pattern déjà
-- utilisé dans party_ pour des contraintes non exprimables proprement
-- en SQL pur (cf. schema-party-account-v1.sql, notes 3 et 13).
-- L'unicité globale de public_id / id reste garantie par IDENTITY
-- (séquence globale, pas par partition).
-- ============================================================
CREATE TABLE booking (
    id                        BIGINT GENERATED BY DEFAULT AS IDENTITY, -- BY DEFAULT (pas ALWAYS) : PK composite (id, booking_date) sur table partitionnée -- Doctrine ORM ne supporte pas la stratégie IDENTITY sur clé composite, pré-assignation via nextval() nécessaire côté backend. Amendement ADR-018, 21/07/2026 -- voir 01-architecture_decisions.md.
    public_id                 UUID NOT NULL DEFAULT gen_random_uuid(),
    booking_date               DATE NOT NULL DEFAULT CURRENT_DATE, -- clé de partition : date de création, pas date de séjour

    folder_id                   BIGINT NOT NULL REFERENCES booking_folder(id),
    service_type_code             VARCHAR(30) NOT NULL REFERENCES booking_service_type(code),
    status_code                     VARCHAR(30) NOT NULL REFERENCES booking_status(code),

    -- Tiers impliqués -- customer_account_id et office_account_id sont
    -- dénormalisés depuis booking_folder au moment de la création
    -- (évite un join sur le chemin de lecture chaud d'une table
    -- massivement partitionnée et interrogée) -- même logique
    -- assumée que party_account.logo_url.
    customer_account_id            BIGINT NOT NULL REFERENCES party_account(id),
    supplier_account_id              BIGINT REFERENCES party_account(id), -- NULLABLE -- confirmé sur donnée réelle : fournisseur_temporaire/"Sans Fournisseur" existe (résa créée avant assignation d'un fournisseur)
    office_account_id                  BIGINT NOT NULL REFERENCES party_account(id),

    -- Dates du service rendu (distinct de booking_date, clé de partition)
    start_date                           DATE NOT NULL, -- check-in, date de départ vol, date excursion...
    end_date                               DATE,          -- check-out... NULL si service ponctuel (transfert, visa)

    -- Devises et taux figés au jour de la réservation, achat et vente
    -- indépendamment (pas de conversion implicite en base)
    achat_currency_code                       VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    achat_exchange_rate                         NUMERIC(14,6) NOT NULL DEFAULT 1,
    vente_currency_code                           VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    vente_exchange_rate                             NUMERIC(14,6) NOT NULL DEFAULT 1,

    -- Montants en centimes (convention Money=BIGINT, 00-project_overview.md),
    -- dénormalisés et maintenus par la couche Application (jamais par
    -- trigger, cf. ADR-002) -- source de vérité pour l'affichage et le
    -- futur module Facturation.
    total_achat_amount                                BIGINT NOT NULL DEFAULT 0, -- centimes devise achat
    total_vente_amount                                  BIGINT NOT NULL DEFAULT 0, -- centimes devise vente
    marge_agence_amount                                   BIGINT NOT NULL DEFAULT 0, -- centimes devise vente
    marge_distributeur_amount                               BIGINT NOT NULL DEFAULT 0, -- centimes devise vente, 0 si pas de distributeur B2B

    paid_amount                                               BIGINT NOT NULL DEFAULT 0, -- dénormalisé depuis SUM(booking_payment.amount)
    payment_status                                              VARCHAR(20) NOT NULL DEFAULT 'unpaid'
                                                                   CHECK (payment_status IN ('unpaid', 'partial', 'paid')),

    -- BOOK_NOW_PAY_LATER : date limite de paiement, fixée une fois à la
    -- création, jamais repoussée (décision actée). Au-delà, un job
    -- planifié (Application, pas un trigger DB) annule automatiquement
    -- via la même Command qu'une annulation manuelle.
    option_expiry_at                                              TIMESTAMPTZ,

    -- Statut fournisseur brut (vocabulaire propre à chaque provider,
    -- non structurant -- distinct de status_code)
    supplier_status_label                                            VARCHAR(100),

    -- "Sur demande" est un AXE INDÉPENDANT du statut principal, pas une
    -- valeur de status_code -- confirmé sur données réelles : une
    -- réservation peut être etat=Validée (status_code='confirmed') ET
    -- surDemande=true ET confirmationHotel pas encore tranché,
    -- simultanément (cf. legacy ost_sht_reservation : etat, surDemande,
    -- confirmationHotel sont 3 colonnes distinctes qui cohabitent). Le
    -- nouveau système PEUT choisir d'imposer une règle plus stricte
    -- (interdire 'confirmed' tant que is_on_request=true), mais c'est
    -- une règle applicative (Domain), pas une contrainte structurelle
    -- qui rendrait les deux états mutuellement exclusifs en base.
    is_on_request                                                      BOOLEAN NOT NULL DEFAULT false, -- raison(s) détaillée(s) dans booking_on_request_flag (1-N -- confirmé : plusieurs raisons simultanées possibles, ex "stock" ET "solde")

    -- Justificatif de calcul -- documentaire uniquement, jamais requêté
    -- ni agrégé en SQL, perte sans gravité (cf. décision : pas de
    -- booking_charge relationnel tant qu'aucun besoin de reporting
    -- transverse n'est avéré)
    price_breakdown                                                    JSONB NOT NULL DEFAULT '{}'::jsonb,

    -- Canal de création (remplace frontOffice + front_office_xml)
    channel_code                                                       VARCHAR(30) NOT NULL REFERENCES booking_channel(code),

    -- Prise en charge -- distinct de created_by : qui a créé n'est pas
    -- forcément qui traite. NULL = "en instance", personne n'a commencé.
    assigned_agent_account_id                                          BIGINT REFERENCES party_account(id),
    assigned_at                                                        TIMESTAMPTZ,

    -- Frais d'annulation RÉELLEMENT appliqués (montant réalisé), distinct
    -- du barème porté par booking_cancellation_policy/tier. Renseignés
    -- au moment où l'annulation est actée.
    cancellation_fee_achat_amount                                      BIGINT NOT NULL DEFAULT 0,
    cancellation_fee_vente_amount                                      BIGINT NOT NULL DEFAULT 0,

    -- Référence de la réservation chez le fournisseur (ex: id_xml legacy),
    -- utile pour retrouver un booking à partir d'une référence externe
    supplier_booking_reference                                         VARCHAR(100),

    -- Lien "prévente -> confirmée" : une réservation provisoire peut
    -- être remplacée par une nouvelle ligne "confirmée" plutôt que
    -- mise à jour en place (confirmé sur données réelles maritime :
    -- bookingOrigine_id/bookingConfirm_id). NULL = booking normal, pas
    -- issu d'une confirmation. FK applicative vers booking.id (voir
    -- note partitionnement).
    origin_booking_id                                                    BIGINT,

    -- Type de trajet -- pertinent pour flight/maritime/train (aller
    -- simple/aller-retour/multi-destination). Stocké tel quel depuis
    -- la source (ex-typeVoyage), PAS dérivé du nombre de
    -- booking_transport_segment -- la dérivation serait ambiguë
    -- (un aller-retour peut avoir un port de retour différent de
    -- l'aller). Besoin d'affichage uniquement, confirmé.
    trip_type                                                              VARCHAR(20)
                                                                              CHECK (trip_type IN ('one_way', 'round_trip', 'multi_destination')),

    voucher_url                                                        VARCHAR(500), -- dernier voucher généré (régénéré en place, pas versionné)

    -- Exclusion de facturation (ex-"masquer" legacy -- renommé pour
    -- exposer le vrai sens métier : test/gracieux/litige, jamais facturé)
    exclude_from_invoicing                                             BOOLEAN NOT NULL DEFAULT false,

    intended_payment_method                                            VARCHAR(20), -- mode de paiement souhaité par le client, avant tout booking_payment réel

    -- Snapshot du contact "réservant" au moment de la résa (confirmé
    -- 15/07) -- B2C : coordonnées du client lui-même (saisies dans le
    -- formulaire, avant la liste des voyageurs). B2B : coordonnées de
    -- l'agence + de la personne qui a passé la réservation (formulaire
    -- masqué côté B2B). Distinct de booking_traveler (voyageurs) ET de
    -- party_account (compte, qui peut changer d'adresse/tel après coup
    -- -- ceci reste figé à l'instant de la résa). Scopé par réservation,
    -- pas par dossier -- confirmé sur donnée réelle (coordonnees vit
    -- sur ost_sht_reservation, pas un niveau au-dessus).
    contact_name                                                        VARCHAR(255),
    contact_phone                                                          VARCHAR(50),
    contact_email                                                             VARCHAR(255),
    contact_address                                                              VARCHAR(500),

    is_locked                                                                       BOOLEAN NOT NULL DEFAULT false, -- verrouillage anti-modification, besoin confirmé pour tout type de service
    is_disputed                                                                        BOOLEAN NOT NULL DEFAULT false, -- litige client ou fournisseur -- simple flag de filtrage rapide, détail dans booking_note

    -- Suivi de traitement (équipe), orthogonal à status_code -- dénormalisé
    -- pour filtrage/affichage rapide, historique complet dans log_activity
    -- (entity_type='booking', event_type='processing_status_change'),
    -- voir décision de session 20/07 (sujets-reportes.md §35).
    processing_status_code                                                             VARCHAR(30) REFERENCES booking_processing_status(code),

    -- Points de vente (ajout 20/07, sujets-reportes.md §3/§30) -- deux
    -- rôles distincts pouvant pointer vers des sites différents : où la
    -- vente a été faite, où le client vient/doit payer.
    sales_point_id                                                                      BIGINT REFERENCES sales_point(id),
    sales_point_payment_id                                                             BIGINT REFERENCES sales_point(id),

    created_at                                                           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                                                             TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                                                               BIGINT REFERENCES party_account(id),
    updated_by                                                                 BIGINT REFERENCES party_account(id),

    CONSTRAINT ck_booking_dates CHECK (end_date IS NULL OR end_date >= start_date),

    PRIMARY KEY (id, booking_date)
) PARTITION BY RANGE (booking_date);

COMMENT ON TABLE booking IS 'Pivot Booking : 1 ligne = 1 service = 1 fournisseur, jamais un service composé. Remplace ost_sht_reservation + tables de détail jour-par-jour (abandonnées, cf. price_breakdown). Un "voyage organisé" = plusieurs booking dans un même booking_folder.';
COMMENT ON COLUMN booking.booking_date IS 'Clé de partition : date de CRÉATION de la réservation (insertion monotone, adaptée au partitionnement). Ne pas confondre avec start_date/end_date (dates du séjour/service).';
COMMENT ON COLUMN booking.price_breakdown IS 'Justificatif de calcul (barème appliqué, détail éventuel). Documentaire uniquement : jamais de SUM/GROUP BY dessus, perte sans impact métier. Si un besoin de reporting transverse structurel apparaît un jour, voir sujets-reportes.md (booking_charge, reporté).';

-- Partitions mensuelles bootstrap (3 mois) — noms convention pg_partman
-- (`_pYYYYMMDD` = borne basse). pg_partman prend le relais ensuite
-- (étape OBLIGATOIRE du déploiement, avant première utilisation — §8).
-- Pas de tranche DEFAULT : choix délibéré (§8). Une tranche manquante doit provoquer un
-- rejet immédiat et visible plutôt qu'une accumulation silencieuse impossible à
-- réorganiser ensuite. La couverture est garantie par pg_partman.
CREATE TABLE booking_p20260701 PARTITION OF booking FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');
CREATE TABLE booking_p20260801 PARTITION OF booking FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');
CREATE TABLE booking_p20260901 PARTITION OF booking FOR VALUES FROM ('2026-09-01') TO ('2026-10-01');

-- NOTE : PostgreSQL exige que tout index UNIQUE sur une table partitionnée
-- inclue la colonne de partition. uq_booking_public_id porte donc sur
-- (public_id, booking_date) et non public_id seul -- l'unicité stricte de
-- public_id repose en pratique sur l'entropie de gen_random_uuid() (risque
-- de collision négligeable, même hypothèse déjà acceptée pour public_id
-- dans party_account, cf. ADR-018).
CREATE UNIQUE INDEX uq_booking_public_id ON booking(public_id, booking_date);
CREATE INDEX idx_booking_folder ON booking(folder_id);
CREATE INDEX idx_booking_customer ON booking(customer_account_id);
CREATE INDEX idx_booking_supplier ON booking(supplier_account_id);
CREATE INDEX idx_booking_office ON booking(office_account_id);
CREATE INDEX idx_booking_status ON booking(status_code);
CREATE INDEX idx_booking_service_type ON booking(service_type_code);
CREATE INDEX idx_booking_start_date ON booking(start_date);
CREATE INDEX idx_booking_option_expiry ON booking(option_expiry_at) WHERE status_code = 'on_option';
CREATE INDEX idx_booking_on_request ON booking(id) WHERE is_on_request = true;
CREATE INDEX idx_booking_unassigned ON booking(booking_date) WHERE assigned_agent_account_id IS NULL; -- file "en instance"
CREATE INDEX idx_booking_supplier_reference ON booking(supplier_booking_reference) WHERE supplier_booking_reference IS NOT NULL;
CREATE INDEX idx_booking_origin ON booking(origin_booking_id) WHERE origin_booking_id IS NOT NULL;
CREATE INDEX idx_booking_disputed ON booking(id) WHERE is_disputed = true;
CREATE INDEX idx_booking_processing_status ON booking(processing_status_code) WHERE processing_status_code IS NOT NULL;
CREATE INDEX idx_booking_sales_point ON booking(sales_point_id) WHERE sales_point_id IS NOT NULL;
CREATE INDEX idx_booking_sales_point_payment ON booking(sales_point_payment_id) WHERE sales_point_payment_id IS NOT NULL;

CREATE TRIGGER trg_booking_updated_at BEFORE UPDATE ON booking
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- booking_accommodation_detail : extension 1-1, EXEMPLE de table de détail
-- spécifique à un service. Volontairement minimale (même principe que
-- party_account_person_identity) -- uniquement ce qui n'a pas sa place
-- dans les champs communs de booking. D'autres services (flight,
-- transfer...) auront leur propre booking_<type>_detail, créée au
-- fur et à mesure du besoin réel -- beaucoup de services (visa, spa)
-- n'en auront probablement aucune.
-- board_type_snapshot (arrangement/pension commercial) vit ICI, au niveau
-- de la réservation entière : une réservation hôtel n'a qu'un seul arrangement,
-- même si elle contient plusieurs chambres (cf. booking_hotel_room).
-- TEXTE LIBRE VOLONTAIRE — distinct de ref_board_type (catalogue).
-- ============================================================
CREATE TABLE booking_accommodation_detail (
    booking_id                   BIGINT PRIMARY KEY, -- FK applicative vers booking.id (voir note partitionnement)
    accommodation_id             BIGINT REFERENCES ref_accommodation(id), -- NULLABLE : résa antérieure à la réconciliation avec le référentiel, ou import legacy jamais rapproché
    accommodation_name_snapshot  VARCHAR(255), -- figé au moment de la réservation -- l'hébergement peut être renommé plus tard, la résa ne doit pas bouger
    board_type_snapshot          VARCHAR(50),  -- libellé commercial de l'arrangement tel qu'annoncé par le fournisseur, figé à la vente -- TEXTE LIBRE VOLONTAIRE, PAS un code de référentiel ; les exemples ci-dessus ne sont pas une liste fermée
    created_at                   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                   TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_accommodation_detail IS 'Extension 1-1 spécifique hébergement, niveau réservation entière (hébergement, pension). Le détail par chambre vit dans booking_hotel_room (1-N). Renommée depuis booking_hotel_detail (20/07) pour cohérence terminologique tout-anglais avec ref_accommodation (sujets-reportes.md §6/§33) -- accommodation_id remplace l''ancien hotel_code (texte libre, jamais relié à un référentiel) par une vraie FK.';

CREATE TRIGGER trg_booking_accommodation_detail_updated_at BEFORE UPDATE ON booking_accommodation_detail
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- booking_hotel_room : une ligne par chambre réservée. Une réservation
-- hôtel legacy (ost_sht_reservation) peut contenir plusieurs chambres
-- (ost_sht_reservation_chambre) sous un même statut/séjour global --
-- confirmé sur données réelles (réservation legacy #1 : 2 chambres).
-- Ne casse pas le principe "1 booking = 1 service = 1 fournisseur" :
-- un séjour multi-chambres reste un seul service hôtelier.
-- ============================================================
CREATE TABLE booking_hotel_room (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id  BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    room_type   VARCHAR(100),    -- 'Chambre Double','Chambre Familiale'...
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_hotel_room IS 'Chambre au sein d''une réservation hôtel. Remplace ost_sht_reservation_chambre. Les voyageurs occupant cette chambre sont liés via booking_traveler.hotel_room_id.';

CREATE INDEX idx_booking_hotel_room_booking ON booking_hotel_room(booking_id);

-- ============================================================
-- booking_transport_segment : une ligne par tronçon (aller, retour,
-- correspondance...). Générique -- réutilisable par flight/train/
-- maritime (même structure : départ/arrivée, lieu, transporteur).
-- Rattaché au booking entier (pas à un voyageur précis) : confirmé sur
-- capture réelle, l'itinéraire est partagé par tous les passagers d'une
-- même réservation. Remplace ost_billetterie_reservation_ligne_trajet.
-- ============================================================
CREATE TABLE booking_transport_segment (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id       BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    sequence_number  SMALLINT NOT NULL DEFAULT 1, -- 1=aller, 2=retour, 3+=correspondances/multi-city
    carrier_code     VARCHAR(50),  -- code/nom transporteur (ex: 'TU','TK' pour vol ; 'GNV Cristal' pour un navire -- confirmé sur capture réelle)
    departure_at     TIMESTAMPTZ NOT NULL,
    arrival_at       TIMESTAMPTZ NOT NULL,
    departure_location VARCHAR(100), -- code/libellé aéroport-gare-port, référentiel dédié hors périmètre
    arrival_location    VARCHAR(100),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_transport_segment IS 'Tronçon de transport (aller/retour/correspondance), générique flight/train/maritime. Remplace ost_billetterie_reservation_ligne_trajet.';

CREATE INDEX idx_booking_transport_segment_booking ON booking_transport_segment(booking_id, sequence_number);

-- ============================================================
-- booking_car_rental_detail : extension 1-1, service_type='car_rental'.
-- pickup_at/dropoff_at en TIMESTAMPTZ -- confirmé sur capture réelle :
-- la durée de location se calcule à l'heure près ("du 25/06 15:00 au
-- 26/06 15:00 soit 1 jour"), contrairement à booking.start_date/end_date
-- (DATE, suffisant pour tous les autres services -- pas modifié pour
-- ne pas complexifier le pivot commun, cf. principe déjà tenu pour
-- board_type/hotel_code).
-- ============================================================
CREATE TABLE booking_car_rental_detail (
    booking_id           BIGINT PRIMARY KEY, -- FK applicative vers booking.id (voir note partitionnement)
    vehicle_category      VARCHAR(100), -- ex: "Mini I10 ou similaire"
    vehicle_brand_model    VARCHAR(100), -- ex: "Hyundai"
    pickup_at                TIMESTAMPTZ,
    dropoff_at                 TIMESTAMPTZ,
    pickup_location              VARCHAR(255),
    dropoff_location               VARCHAR(255), -- NULL/= pickup_location si restitution au même endroit
    created_at                       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                          TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_car_rental_detail IS 'Extension 1-1 spécifique location de voiture. pickup_at/dropoff_at portent la précision horaire que booking.start_date/end_date (DATE) n''offre pas.';

CREATE TRIGGER trg_booking_car_rental_detail_updated_at BEFORE UPDATE ON booking_car_rental_detail
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- ============================================================
-- booking_traveler : voyageur, DIRECTEMENT rattaché à un booking (plus
-- de table de liaison N-N : un voyageur est déjà un instantané figé
-- propre à UNE réservation, jamais réutilisé tel quel sur une autre --
-- inutile de modéliser une association many-to-many pour un lien qui
-- est en réalité toujours 1-N depuis booking). party_account_id
-- NULLABLE -- peut être un client existant (historisable via une
-- requête sur party_account_id à travers tous ses booking_traveler)
-- OU une saisie libre (ex-"passager" legacy). is_pax_leader porté
-- directement ici (un seul par booking, contrainte ci-dessous) --
-- concept distinct du client payeur (booking_folder.party_account_id).
-- age ET birth_date coexistent volontairement : l'hôtel ne demande
-- souvent que l'âge, le vol/maritime exige la date de naissance exacte
-- -- pas un doublon de modélisation, un vrai besoin métier différent
-- selon service_type.
-- ============================================================
CREATE TABLE booking_traveler (
    id                      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id              BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    hotel_room_id           BIGINT REFERENCES booking_hotel_room(id), -- NULL sauf service_type='hotel' avec chambre assignée
    party_account_id        BIGINT REFERENCES party_account(id), -- NULL = saisie libre, pas de compte

    first_name              VARCHAR(150) NOT NULL,
    last_name               VARCHAR(150) NOT NULL,
    civility                VARCHAR(10),  -- 'Mr','Mrs','Ms'...
    phone                   VARCHAR(50),  -- coordonnées du VOYAGEUR (figées), distinct du contact payeur sur party_account
    email                    VARCHAR(255),
    age                     SMALLINT,     -- suffisant pour l'hôtel (ex: "enfant 6 ans" sans date exacte)
    birth_date              DATE,         -- exigé pour vol/maritime (pièce d'identité)
    birth_place              VARCHAR(150), -- confirmé sur capture réelle maritime (ex: "KASSERINE")
    nationality_country_id  BIGINT,       -- FK référentiel statique pays, hors périmètre de ce script
    residence_country_id    BIGINT,       -- lieu de résidence, parfois obligatoire (vol/maritime) -- même référentiel

    document_type             VARCHAR(30) REFERENCES ref_document_type(code),  -- pièce d'identité ; confirmé nécessaire pour vol/maritime international
    document_number            VARCHAR(50),
    driving_license_number      VARCHAR(50), -- pertinent pour service_type='car_rental' (conducteur), NULL sinon -- confirmé sur capture réelle

    is_pax_leader            BOOLEAN NOT NULL DEFAULT false,

    -- Champs billet -- pertinents pour service_type in ('flight','train','maritime'),
    -- NULL sinon. Confirmé par capture réelle : num_billet + PNR + classe
    -- sont propres à CHAQUE voyageur, pas au booking entier.
    ticket_number             VARCHAR(50),  -- ex: "124-2445435844"
    pnr                       VARCHAR(20),
    travel_class              VARCHAR(10),  -- ex: "B" (classe tarifaire)

    created_at                TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_traveler IS 'Voyageur, snapshot figé (nom/prénom/naissance...) même si party_account_id est renseigné -- une réservation reste un instantané historique. Remplace ost_sht_reservation_personne ET la liaison chambre<->personne du legacy (hotel_room_id remplace reservationChambreAdulte_id/reservationChambreEnfant_id).';

CREATE UNIQUE INDEX uq_booking_traveler_pax_leader ON booking_traveler(booking_id) WHERE is_pax_leader = true;
CREATE INDEX idx_booking_traveler_booking ON booking_traveler(booking_id);
CREATE INDEX idx_booking_traveler_hotel_room ON booking_traveler(hotel_room_id) WHERE hotel_room_id IS NOT NULL;
CREATE INDEX idx_booking_traveler_account ON booking_traveler(party_account_id) WHERE party_account_id IS NOT NULL;

-- ============================================================
-- booking_payer_split : répartition du montant à PAYER (côté client)
-- entre plusieurs party_account (ex : amicale/employé). Toujours par
-- montant fixe (pas de pourcentage). Historisée (valid_from/valid_to,
-- même pattern que party_account_role) car modifiable après coup
-- (ex: 100% amicale au départ -> 300/700 employé/amicale ensuite).
-- Ne PAS confondre avec booking_settlement (répartition côté
-- bénéficiaires agence/fournisseur, question différente).
-- ============================================================
CREATE TABLE booking_payer_split (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id        BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    payer_account_id  BIGINT NOT NULL REFERENCES party_account(id),
    amount            BIGINT NOT NULL, -- centimes, devise vente du booking
    valid_from        TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to          TIMESTAMPTZ, -- NULL = répartition active
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by        BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_payer_split IS 'Répartition du montant à payer entre plusieurs payeurs (ex: amicale/employé), par montant fixe, historisée. SUM(amount) des lignes actives d''un booking doit égaler booking.total_vente_amount (règle applicative, pas une contrainte SQL -- même logique que party_ ailleurs dans le projet).';

CREATE UNIQUE INDEX uq_booking_payer_split_active
    ON booking_payer_split(booking_id, payer_account_id) WHERE valid_to IS NULL;
CREATE INDEX idx_booking_payer_split_booking ON booking_payer_split(booking_id) WHERE valid_to IS NULL;
CREATE INDEX idx_booking_payer_split_payer ON booking_payer_split(payer_account_id) WHERE valid_to IS NULL;

-- ============================================================
-- booking_settlement : une ligne par bénéficiaire de la chaîne
-- (fournisseur / agence principale / distributeur B2B -- plusieurs
-- lignes possibles avec beneficiary_role='distributor' si un
-- distributeur a lui-même des sous-comptes, chacun avec son propre
-- beneficiary_account_id).
--
-- RÈGLE DE RÉCONCILIATION (confirmée le 15/07) : Total Vente - Total
-- Achat (calculé depuis booking_charge) = la marge totale, déjà
-- "comptée" par construction -- c'est l'écart achat/vente sur les
-- lignes booking_charge elles-mêmes qui la porte (implicitement la
-- marge de l'agence principale, qui fixe ces prix). booking_settlement
-- ne fait que REDÉCOUPER cette marge déjà comptée entre bénéficiaires
-- -- jamais une somme additionnelle au total. amount_owed d'une ligne
-- 'distributor' (et de ses éventuels sous-comptes) est donc purement
-- informationnel/descriptif, prélevé sur la marge déjà incluse dans
-- booking_charge, jamais ajouté par-dessus.
--
-- DISTINCT DE resale_price_amount (confirmé le 15/07) : un bénéficiaire
-- (typiquement le distributeur) peut revendre à SON PROPRE client à un
-- prix encore supérieur à ce qu'il nous paie -- une 3e transaction,
-- hors du périmètre financier de CE booking (on ne l'encaisse jamais).
-- resale_price_amount est purement informatif, ne participe à AUCUN
-- calcul de total ni de marge redécoupée ci-dessus.
--
-- Constate aussi un FAIT de règlement (montant dû dans le circuit
-- normal, et montant réglé HORS circuit agence, ex: client règle
-- l'hôtel en direct). Ne calcule PAS d'échéance -- Booking constate, le
-- futur module Cash Management/Finance lit ces faits pour calculer
-- soldes/échéances réels, compensables dans le temps entre plusieurs
-- réservations (cf. sujets-reportes.md, points 2/2bis). Historisée
-- comme booking_payer_split car un règlement direct peut être constaté
-- après coup (le client informe l'agence après la réservation
-- initiale).
-- ============================================================
CREATE TABLE booking_settlement (
    id                     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id             BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    beneficiary_account_id BIGINT NOT NULL REFERENCES party_account(id),
    beneficiary_role       VARCHAR(30) NOT NULL
                               CHECK (beneficiary_role IN ('supplier', 'main_agency', 'distributor')),
    amount_owed            BIGINT NOT NULL, -- centimes, ce qui revient à ce bénéficiaire en circuit normal
    amount_settled_direct  BIGINT NOT NULL DEFAULT 0, -- centimes, réglé hors circuit agence, constaté factuellement
    rate                    NUMERIC(6,3), -- taux d'origine si le montant a été calculé par pourcentage (ex: "Commission B2B 50%") -- optionnel, pour réafficher le taux tel quel plutôt que le recalculer depuis les montants (arrondis)
    resale_price_amount     BIGINT, -- prix auquel CE bénéficiaire revend à SON PROPRE client final (ex: WIKO facture 1812.132 à son client, alors qu'on facture 1510.110 à WIKO) -- confirmé sur donnée réelle (ex-marge_b2b). PUREMENT INFORMATIF : ne participe jamais à la réconciliation booking_charge/total_vente_amount, cette transaction se passe hors de notre périmètre financier
    currency_code          VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    valid_from              TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to                TIMESTAMPTZ,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_settlement IS 'Faits de règlement par bénéficiaire (fournisseur/agence principale/distributeur). Le futur Cash Management calcule "à verser réellement = amount_owed - amount_settled_direct" par bénéficiaire, sans recalculer l''historique. Booking ne génère aucune échéance.';

CREATE UNIQUE INDEX uq_booking_settlement_active
    ON booking_settlement(booking_id, beneficiary_role, beneficiary_account_id) WHERE valid_to IS NULL;
CREATE INDEX idx_booking_settlement_booking ON booking_settlement(booking_id) WHERE valid_to IS NULL;
CREATE INDEX idx_booking_settlement_beneficiary ON booking_settlement(beneficiary_account_id) WHERE valid_to IS NULL;

-- ============================================================
-- booking_payment : paiement effectif REÇU pour une réservation (carte
-- en ligne, cash, virement...). Rattaché à un booking_payer_split
-- (nullable si paiement global, non scindé par payeur). Plusieurs
-- paiements possibles par split (acompte + solde). Pour les clients en
-- compte courant non-lettré (B2B), cette table reste simplement vide
-- -- le rapprochement relève entièrement du futur module Finance
-- (cf. sujets-reportes.md, lettrage reporté).
-- ============================================================
CREATE TABLE booking_payment (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id         BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    payer_split_id     BIGINT REFERENCES booking_payer_split(id), -- NULL = paiement global, non scindé
    collected_by_account_id BIGINT REFERENCES party_account(id), -- agent/caissier ayant physiquement encaissé -- NULLABLE (legacy ne l'a pas systématiquement, sujets-reportes.md §30)

    amount             BIGINT NOT NULL, -- centimes
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    method             VARCHAR(20) NOT NULL
                           CHECK (method IN ('card', 'cash', 'bank_transfer', 'wallet', 'other')),
    provider_reference VARCHAR(255), -- ex: id transaction Stripe -- idempotence webhook
    status             VARCHAR(20) NOT NULL
                           CHECK (status IN ('pending', 'captured', 'failed', 'refunded')),
    paid_at            TIMESTAMPTZ,

    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_payment IS 'Paiement effectif reçu pour une réservation. Un remboursement se matérialise par une NOUVELLE ligne (montant négatif ou status=refunded sur la ligne d''origine, à trancher en Application) -- jamais un UPDATE qui efface l''historique d''une transaction déjà capturée.';

CREATE UNIQUE INDEX uq_booking_payment_provider_ref ON booking_payment(provider_reference)
    WHERE provider_reference IS NOT NULL;
CREATE INDEX idx_booking_payment_booking ON booking_payment(booking_id);
CREATE INDEX idx_booking_payment_split ON booking_payment(payer_split_id) WHERE payer_split_id IS NOT NULL;

CREATE TRIGGER trg_booking_payment_updated_at BEFORE UPDATE ON booking_payment
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- TABLE DE RÉFÉRENCE : booking_charge_type
-- Table, pas ENUM (même principe que booking_service_type/party_role).
-- ============================================================
CREATE TABLE booking_charge_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO booking_charge_type (code, sort_order) VALUES
    ('room_rate',                 0), -- prix de base (agrégé -- le détail jour/chambre/personne reste dans price_breakdown)
    ('discount',                  1), -- remise (ex-remise/remise_internet legacy)
    ('margin_main_agency',  2),
    ('margin_distributor',       3), -- 0 lignes si pas de distributeur B2B
    ('file_fee',                  4), -- ex-frais_dossier
    ('fiscal_stamp',              5), -- ex-timbre
    ('city_tax',                  6), -- ex-taxe de séjour (revirement du 15/07 : rejoint aussi booking_charge)
    ('fare',                      7), -- prix du billet (équivalent room_rate pour vol/train/maritime -- "Debours Achat")
    ('service_fee',               8), -- frais de service agence (ex "Frais Service")
    ('commission',                9), -- commission perçue du fournisseur (ex "Commission Achat") -- distinct de la marge
    ('withholding_tax',          10), -- retenue à la source (ex retenu_a_la_source, billetterie)
    ('vehicle_transport',        11), -- transport de véhicule (maritime), label libre pour plaque/dimensions
    ('accommodation',            12), -- hébergement à bord (cabine/fauteuil maritime), distinct de 'fare'
    ('rental_base',               13), -- location de base (voiture) -- confirmé sur capture réelle
    ('pickup_fee',                14), -- frais lieu de prise en charge
    ('dropoff_fee',               15), -- frais lieu de restitution
    ('supplement',                16), -- supplément optionnel (ex: chaise bébé, conducteur additionnel)
    ('transfer_fee',              17), -- prix transfert point A -> point B
    ('passenger_insurance',       18), -- assurance passager (maritime, confirmé sur donnée réelle) -- rattachée à traveler_id
    ('vehicle_insurance',         19), -- assurance véhicule (maritime) -- rattachée à segment_id
    ('meal',                      20), -- repas/pass restauration (maritime), souvent inclus (TotalPrice=0) -- confirmé sur donnée réelle
    ('refund',                    21), -- remboursement suite annulation, montant négatif -- rattaché à segment_id si propre à un tronçon (confirmé : remboursement distinct aller/retour)
    ('other',                    99);

CREATE TABLE booking_charge_type_translation (
    charge_type_code  VARCHAR(30) NOT NULL REFERENCES booking_charge_type(code),
    language_code     VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label             VARCHAR(100) NOT NULL,
    PRIMARY KEY (charge_type_code, language_code)
);

-- ============================================================
-- booking_charge : décomposition AGRÉGÉE du prix d'une réservation
-- (prix chambre, remise, marges, frais de dossier, timbre...), telle
-- qu'affichée "en bas, avant le total final" -- PAS le détail
-- jour/chambre/personne (qui reste dans booking.price_breakdown JSONB,
-- affichage uniquement, jamais requêté). SUM(vente_amount) doit égaler
-- booking.total_vente_amount -- règle applicative (Application
-- recalcule et réécrit le total à chaque mutation, jamais un trigger,
-- cf. ADR-002), pas une contrainte SQL.
-- ============================================================
CREATE TABLE booking_charge (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id        BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    traveler_id       BIGINT REFERENCES booking_traveler(id), -- NULL = ligne agrégée au niveau booking (ex: marge) ; renseigné = ligne propre à un passager (ex: tarif billet -- confirmé sur capture réelle billetterie)
    segment_id        BIGINT REFERENCES booking_transport_segment(id), -- NULL = ligne non liée à un segment précis ; renseigné = prix propre à ce tronçon (ex: véhicule/hébergement à bord, différents aller/retour -- confirmé sur capture réelle maritime)
    charge_type_code  VARCHAR(30) NOT NULL REFERENCES booking_charge_type(code),
    label             VARCHAR(255), -- libellé COURT affiché (ex: "Transport véhicule"), NULL = libellé du charge_type par défaut
    metadata          JSONB NOT NULL DEFAULT '{}'::jsonb, -- détail verbeux propre à CETTE ligne (ex: plaque/dimensions véhicule) -- jamais filtré/agrégé, vide sur la quasi-totalité des lignes (négligeable en volume, contrairement à un VARCHAR systématiquement rempli)
    achat_amount      BIGINT NOT NULL DEFAULT 0, -- centimes, devise achat du booking
    vente_amount      BIGINT NOT NULL DEFAULT 0, -- centimes, devise vente du booking
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_charge IS 'Décomposition agrégée du prix (pas de détail jour/chambre/personne, qui reste dans price_breakdown). Remplace ost_sht_reservation_ligne au niveau agrégat, pas au niveau détail. metadata porte le détail verbeux propre à une ligne précise (ex: véhicule maritime), jamais requêté.';

CREATE INDEX idx_booking_charge_booking ON booking_charge(booking_id, sort_order);
CREATE INDEX idx_booking_charge_traveler ON booking_charge(traveler_id) WHERE traveler_id IS NOT NULL;
CREATE INDEX idx_booking_charge_segment ON booking_charge(segment_id) WHERE segment_id IS NOT NULL;

-- ============================================================
-- booking_cancellation_policy / booking_cancellation_tier : barème
-- d'annulation. CORRECTION du 15/07 : le legacy attache le barème par
-- CHAMBRE, pas pour toute la réservation -- confirmé explicitement
-- (une réservation multi-chambres peut avoir des conditions
-- différentes par chambre, ex: chambre en promo = non remboursable,
-- chambre normale = flexible). room_id nullable : NULL = barème pour
-- toute la réservation (services sans notion de chambre -- vol,
-- transfert...), renseigné = barème propre à CETTE chambre (cas
-- normal pour l'hôtel). Sert aussi à déterminer l'éligibilité
-- BOOK_NOW_PAY_LATER (annulation gratuite à l'instant T = pas de
-- charge -> option proposable), calcul fait en Application, pas stocké
-- séparément.
-- ============================================================
CREATE TABLE booking_cancellation_policy (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id  BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    room_id     BIGINT REFERENCES booking_hotel_room(id), -- NULL = barème pour toute la réservation ; renseigné = barème propre à cette chambre (cas normal hôtel, confirmé)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 1 seule politique "toute la réservation" (room_id NULL) par booking,
-- et 1 seule politique par chambre si room_id renseigné.
CREATE UNIQUE INDEX uq_booking_cancellation_policy_booking ON booking_cancellation_policy(booking_id) WHERE room_id IS NULL;
CREATE UNIQUE INDEX uq_booking_cancellation_policy_room ON booking_cancellation_policy(room_id) WHERE room_id IS NOT NULL;
CREATE INDEX idx_booking_cancellation_policy_booking ON booking_cancellation_policy(booking_id);

CREATE TABLE booking_cancellation_tier (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    policy_id         BIGINT NOT NULL REFERENCES booking_cancellation_policy(id),
    days_before_start INT NOT NULL,   -- seuil en jours avant start_date du booking
    threshold_time    TIME,           -- seuil à une heure précise, en plus du jour (confirmé sur donnée réelle : "horaire") -- NULL = pas de seuil horaire, jour seul
    min_stay_nights   SMALLINT,       -- palier applicable seulement si le séjour dure au moins N nuits (confirmé : "min_stay") -- NULL = pas de borne basse
    max_stay_nights   SMALLINT,       -- borne haute équivalente ("max_stay") -- NULL = pas de borne haute
    penalty_type      VARCHAR(20) NOT NULL
                          CHECK (penalty_type IN ('free', 'percentage', 'fixed_amount')),
    penalty_value     NUMERIC(14,3), -- % (0-100) si percentage, montant si fixed_amount, NULL si free
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_cancellation_policy IS 'Barème d''annulation. Confirmé : rattaché PAR CHAMBRE en général pour l''hôtel (room_id renseigné), pas à la réservation entière -- correction du 15/07 après cas réel.';
COMMENT ON TABLE booking_cancellation_tier IS 'Paliers du barème (ex: >30j = free, 15-30j = 30%, <15j = 100%). Consommé aussi pour déterminer l''éligibilité BOOK_NOW_PAY_LATER (annulation gratuite à l''instant T). NatureAnnulation/Type du legacy non repris tels quels (sémantique ambiguë, sans libellé) -- à clarifier si un vrai besoin de distinction apparaît au-delà de penalty_type.';

CREATE INDEX idx_booking_cancellation_tier_policy ON booking_cancellation_tier(policy_id);

-- ============================================================
-- booking_approval : événements de confirmation/rejet/approbation.
-- approval_type distingue QUI doit agir : 'supplier' (le fournisseur/
-- hôtel doit confirmer -- typiquement quand is_on_request=true),
-- 'internal' (un responsable de l'agence doit approuver -- ex: solde
-- B2B insuffisant, ou compte nécessitant systématiquement une
-- validation avant résa, critère à définir en Application), ou
-- 'cancellation_request' (un B2B demande l'annulation sans pouvoir
-- l'exécuter lui-même, l'agence tranche -- conservé du legacy).
-- method distingue COMMENT la décision a été capturée : 'automatic'
-- (clic sur le lien dans l'email envoyé au fournisseur) ou 'manual'
-- (saisie par un agent de l'agence). Plusieurs lignes possibles par
-- booking dans le temps (rejeté une première fois, puis reproposé et
-- confirmé) -- table d'événements, jamais écrasée, même logique que
-- booking_settlement/booking_payment : on constate des faits.
-- ============================================================
CREATE TABLE booking_approval (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id        BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    approval_type     VARCHAR(20) NOT NULL CHECK (approval_type IN ('supplier', 'internal', 'cancellation_request')),
    outcome           VARCHAR(20) NOT NULL DEFAULT 'pending'
                          CHECK (outcome IN ('pending', 'confirmed', 'rejected')),
    method            VARCHAR(20) CHECK (method IN ('automatic', 'manual')), -- NULL tant que pending
    actor_account_id  BIGINT REFERENCES party_account(id), -- agent si method='manual', NULL si automatique ou pending

    requested_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    response_deadline TIMESTAMPTZ, -- délai laissé au tiers pour répondre (confirmé : ex-date_expiration_proposition_hotel) -- distinct de booking.option_expiry_at (délai client, pas fournisseur)
    decided_at        TIMESTAMPTZ, -- NULL tant que pending

    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_approval IS 'Historique des demandes de confirmation/approbation (fournisseur ou interne) et de leur issue. Une ligne "pending" transitionne en place vers confirmed/rejected (même requête, pas un nouveau fait) ; une nouvelle tentative après rejet crée une nouvelle ligne.';

CREATE INDEX idx_booking_approval_booking ON booking_approval(booking_id);
CREATE INDEX idx_booking_approval_pending ON booking_approval(approval_type) WHERE outcome = 'pending'; -- file d'attente (relance fournisseur / approbation interne)

CREATE TRIGGER trg_booking_approval_updated_at BEFORE UPDATE ON booking_approval
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- booking_note : consolide 5 champs texte flous du legacy
-- (commentaire, observation, texte, remarques, conditions_vente) en
-- une seule structure typée. note_type distingue l'AUDIENCE :
-- 'internal' (jamais visible du client), 'client_facing' (affiché sur
-- voucher/confirmation), 'sales_conditions' (conditions de vente
-- affichées au client). Plusieurs notes possibles, attribuées
-- (qui, quand) -- répond enfin à "qui a écrit quoi et pour qui",
-- ce qu'un longtext unique ne permettait pas.
-- ============================================================
CREATE TABLE booking_note (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id  BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    note_type   VARCHAR(20) NOT NULL CHECK (note_type IN ('internal', 'client_facing', 'sales_conditions')),
    content     TEXT NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_note IS 'Remplace commentaire/observation/texte/remarques/conditions_vente (legacy). Une ligne par note, typée par audience, attribuée.';

CREATE INDEX idx_booking_note_booking ON booking_note(booking_id, note_type);

-- ============================================================
-- booking_revision : snapshot des champs STRUCTURANTS (dates, chambres,
-- pension...) avant une modification post-confirmation -- PAS le détail
-- du calcul de prix (qui reste dans price_breakdown, non concerné ici).
-- Objectif unique et précis (clarifié) : pouvoir notifier le
-- fournisseur qu'il s'agit d'une MODIFICATION et non d'une nouvelle
-- création, avec les anciennes et nouvelles valeurs. Remplace
-- old_versions (legacy), volontairement recentré sur ce seul usage --
-- pas un audit log générique de toutes les colonnes.
-- ============================================================
CREATE TABLE booking_revision (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id  BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    snapshot    JSONB NOT NULL, -- état structurant AVANT modification (dates, chambres, pension, service_type...)
    reason      VARCHAR(100),   -- pourquoi cette modification (saisie libre ou motif court)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_revision IS 'Snapshot avant modification post-confirmation, à usage unique : notifier le fournisseur (ancien vs nouveau). Ne PAS y mettre le détail de calcul de prix (price_breakdown s''en charge déjà).';

CREATE INDEX idx_booking_revision_booking ON booking_revision(booking_id);

-- ============================================================
-- booking_log SUPPRIMÉE (20/07/2026) -- généralisée en log_activity
-- (module transverse log_, schema-log-v1.sql), entity_type='booking'.
-- Comportement préservé à l'identique : event_type/description/metadata/
-- actor_account_id/ip_address/created_at inchangés, entity_id=booking.id.
-- status_code_snapshot (ex-colonne dédiée) déplacé dans metadata
-- ({"status_code_snapshot": "..."}) -- voir sujets-reportes.md §19 et
-- modele-conceptuel-log.md pour le détail complet de la migration.
-- schema-log-v1.sql doit être exécuté après schema-party-account-v1.sql
-- et avant (ou indépendamment de) ce script.
-- ============================================================


-- ============================================================
-- booking_provider_snapshot : payload API brut fournisseur (JSONB),
-- table SÉPARÉE pour sortir les gros blobs du chemin de lecture chaud
-- de booking (évite le TOAST/bloat sur la table la plus lue du
-- module). Purement traçabilité technique, jamais requêté en masse.
-- ============================================================
CREATE TABLE booking_provider_snapshot (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id   BIGINT NOT NULL, -- FK applicative vers booking.id (voir note partitionnement)
    direction    VARCHAR(10) NOT NULL CHECK (direction IN ('request', 'response')),
    payload      JSONB NOT NULL,
    captured_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_provider_snapshot IS 'Payload API brut (request/response) fournisseur, isolé de booking pour ne jamais peser sur son chemin de lecture. Politique de purge à définir (cf. sujets-reportes.md, RGPD/purge point déjà ouvert côté party_).';

CREATE INDEX idx_booking_provider_snapshot_booking ON booking_provider_snapshot(booking_id);

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. PARTITIONNEMENT ET FK APPLICATIVES (décision structurante) :
--    booking est partitionné par RANGE(booking_date), donc sa PK est
--    composite (id, booking_date) -- contrainte native PostgreSQL.
--    Plutôt que de propager booking_date sur chaque table fille
--    (booking_settlement, booking_payment, booking_traveler,
--    booking_hotel_room, booking_cancellation_policy,
--    booking_provider_snapshot, booking_accommodation_detail...) pour permettre
--    une FK SQL composite,
--    on assume une FK applicative sur booking_id seul (BIGINT,
--    globalement unique via IDENTITY, indépendamment des partitions),
--    avec un index btree simple pour la performance des jointures.
--    Cohérent avec le précédent déjà posé dans party_ (règles "nature"
--    et "office_account_id doit porter party_account_office" -- non
--    exprimables en contrainte SQL pure, cf. schema-party-account-v1.sql
--    notes 3 et 13). L'intégrité est garantie par la couche Application
--    (Domain/Repository), jamais par un DELETE CASCADE MySQL comme
--    dans le legacy.
-- 2. AUCUN TRIGGER MÉTIER : les totaux (total_achat_amount,
--    total_vente_amount, marge_*, paid_amount, payment_status) sont
--    dénormalisés mais recalculés et réécrits par la couche Application
--    dans la même transaction que toute mutation qui les affecte
--    (nouveau booking_payment, nouveau booking_settlement...) -- jamais
--    par un trigger DB (ADR-002). Discipline applicative à tenir :
--    aucun UPDATE SQL direct hors Use Case dédié.
-- 3. BOOK_NOW_PAY_LATER : option_expiry_at est fixé une fois à la
--    création, jamais modifié ensuite (décision actée). L'annulation
--    automatique à l'échéance est un job planifié (scheduler côté
--    Application) qui rejoue la même Command qu'une annulation
--    manuelle -- pas de logique d'expiration en base.
-- 4. booking_settlement ne calcule ni ne génère d'échéances : il
--    constate des faits (amount_owed / amount_settled_direct) par
--    bénéficiaire. Le calcul réel de solde/échéance (compensable dans
--    le temps, multi-réservations, multi-devise) est délégué à un
--    futur module Finance/Cash Management via une interface
--    (SolvencyCheckerInterface côté Domain, stub retournant toujours
--    true en attendant -- même pattern qu'ADR-017/FakePermissionChecker).
-- 5. Taxe de séjour, timbre fiscal, frais de dossier : PAS de champ
--    dédié sur booking, PAS de réservation séparée non plus (décisions
--    initiales révisées le 15/07 après clarification de l'usage réel) --
--    ce sont des lignes booking_charge (charge_type_code='city_tax'/
--    'fiscal_stamp'/'file_fee'), agrégées, contribuant au total de LA
--    MÊME réservation hôtelière. Restent des booking séparés : tout
--    service ayant un fournisseur et un cycle de vie réellement
--    indépendants (vol, transfert, excursion...).
-- 6. booking_charge : décomposition AGRÉGÉE du prix (prix chambre,
--    remise, marges, frais divers) -- volontairement sans détail
--    jour/chambre/personne (qui reste dans price_breakdown JSONB,
--    affichage uniquement). Réintroduit après un premier rejet (pas de
--    besoin identifié), sur la base d'un vrai besoin métier confirmé :
--    factures/reporting ont besoin de lignes, pas seulement d'un total.
--    SUM(booking_charge.vente_amount) doit égaler booking.total_vente_amount
--    -- règle applicative (Application recalcule et réécrit à chaque
--    mutation), jamais une contrainte SQL ni un trigger (ADR-002).
-- 7. Détail jour-par-jour (saison, allotement, nuitée) volontairement
--    absent -- remplacé par price_breakdown JSONB, documentaire, non
--    structurel. La saison/allotement en tant que référentiel relève
--    du futur module Contracting.
-- 8. Ce script requiert schema-party-account-v1.sql (party_account,
--    party_account_office, fonction set_updated_at()) et
--    schema-ref-common.sql (ref_language, ref_currency) exécutés au
--    préalable.
-- 9. Partitions de booking : 3 mois bootstrap figés (p20260701..p20260901,
--    convention de nommage pg_partman). Sans tranche DEFAULT (§8) : une
--    tranche manquante REJETTE l'écriture. pg_partman est une étape
--    OBLIGATOIRE du déploiement (avant première utilisation), pas une
--    tâche de maintenance ultérieure — cf.
--    docs/decisions/2026-07-24-pg-partman-deploiement.md.
-- 10. MAPPING DE MIGRATION -- ost_sht_reservation.etat (legacy, 3 valeurs
--    rigides : 1=Enregistré, 2=Validée, 3=Annulée) vers booking.status_code :
--    1 -> 'draft', 2 -> 'confirmed', 3 -> 'cancelled'. Logique d'ETL au
--    moment de la migration, aucune table de correspondance nécessaire
--    en DB -- booking_status reste volontairement plus riche que le
--    legacy (ajout de 'on_option'/'completed'/'no_show').
-- 11. Timbre fiscal (legacy : 3 colonnes timbre/timbre_b2b/timbre_fournisseur
--    sur ost_sht_reservation) -- PAS de colonnes sur booking. Ligne
--    booking_charge (charge_type_code='fiscal_stamp'), voir note 5/6.
-- 12. "Amicale" (legacy : amicale_id/marge_amicale/taux_marge_amicale sur
--    ost_sht_reservation) -- PAS de notion dédiée. Une amicale est un
--    party_account comme un autre, qui peut apparaître dans
--    booking_payer_split au même titre qu'un employé ou tout autre
--    payeur. beneficiary_role de booking_settlement reste à 3 valeurs
--    (fournisseur/main_agency/distributeur) -- l'amicale n'est
--    jamais un bénéficiaire de marge, seulement un payeur éventuel.
-- ============================================================
