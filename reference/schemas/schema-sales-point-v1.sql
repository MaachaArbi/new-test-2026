-- ============================================================
-- Module         : Point de vente (sales_point_)
-- Objet          : Site physique secondaire rattaché à un bureau
--                   (party_account_office), sans identité fiscale
--                   propre -- volontairement PAS un party_account.
--                   Table de référence légère : aucun rôle
--                   transactionnel, aucune contrainte métier sur son
--                   usage en réservation/paiement (confirmé : le
--                   client est libre de choisir n'importe quel site
--                   sans conséquence métier).
--                   Deux usages réels confirmés (échange du 17/07/2026) :
--                   1) liste affichée au client sur le site web pour
--                      choisir où "payer à l'agence"
--                   2) dimension de reporting (regroupement de
--                      réservations par site) -- le rapport de
--                      rendement lui-même (primes agents) est HORS
--                      PÉRIMÈTRE, voir sujets-reportes.md.
-- Version        : 1.0 - Conception du 17/07/2026, validée sur
--                   PostgreSQL 16 réel (aucune donnée réelle de
--                   points de vente disponible à ce jour -- structure
--                   à reconfronter dès qu'un export sera fourni).
-- Date           : 2026-07-17
-- Réfs           : ADR-004 (isolation 1 serveur = 1 client), ADR-018
--                   (BIGINT identity + public_id)
-- Dépend de      : party_account, party_account_office (module party_,
--                   voir schema-party-account-v1.sql) — À EXÉCUTER AVANT
--                   set_updated_at() — définie dans schema-party-account-v1.sql,
--                   réutilisée ici (à recréer si ce module est déployé
--                   indépendamment)
-- ============================================================
-- Tables legacy remplacées : ost_sales_point (structure jamais reprise
-- telle quelle -- conception neuve à partir du besoin réel exposé,
-- voir modele-conceptuel-sales_point.md)
-- Hors périmètre volontaire (voir sujets-reportes.md) :
--   - Rattachement utilisateur/agent <-> point de vente (module 7,
--     utilisateurs avancés/permissions/franchises)
--   - Rapport de rendement agents/points de vente et politique de
--     primes (dépend du module 7, non tranché métier)
--   - Code court / numérotation par site (aucun besoin réel confirmé
--     à ce jour -- colonne nullable facile à ajouter plus tard)
--   - Horaires d'ouverture, géolocalisation (aucune valeur fonctionnelle
--     réelle identifiée, écarté explicitement pour éviter la
--     sur-ingénierie)
-- ============================================================

CREATE TABLE sales_point (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),

    office_account_id  BIGINT NOT NULL REFERENCES party_account(id),
        -- doit porter party_account_office -- règle applicative, pas
        -- une contrainte SQL (même convention que
        -- party_account_office_relation.office_account_id)

    name               VARCHAR(150) NOT NULL,

    -- Fiche propre (confirmé : distincte de celle du bureau)
    address_line1      VARCHAR(255),
    address_line2      VARCHAR(255),
    city               VARCHAR(100),
    postal_code        VARCHAR(20),
    country_id         BIGINT,  -- FK référentiel statique pays, hors périmètre de ce script
    phone              VARCHAR(30),
    contact_email      VARCHAR(255),

    is_active          BOOLEAN NOT NULL DEFAULT true,
        -- désactivation simple, jamais de suppression physique -- les
        -- réservations passées continuent de référencer le site
        -- (confirmé 17/07 : pas de deleted_at sur cette table)

    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id),
    updated_by         BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE sales_point IS 'Site physique secondaire rattaché à un party_account_office. Volontairement PAS un party_account : pas d''identité fiscale, aucun rôle transactionnel/financier -- simple dimension de référence pour affichage client et reporting. Ne pas y ajouter de logique de règlement, de plafond ou de fonction/permission -- ça romprait la frontière party_account = acteur économique.';
COMMENT ON COLUMN sales_point.office_account_id IS 'Doit référencer un party_account portant party_account_office -- règle applicative, pas une contrainte SQL (même logique que party_account_office_relation.office_account_id, cf. schema-party-account-v1.sql).';
COMMENT ON COLUMN sales_point.is_active IS 'Désactivation simple (site fermé). Jamais de suppression physique : l''historique de réservation (booking.sales_point_id / sales_point_payment_id, à venir côté Booking) doit rester résolvable indéfiniment.';

CREATE INDEX idx_sales_point_office ON sales_point(office_account_id) WHERE is_active = true;
CREATE UNIQUE INDEX uq_sales_point_public_id ON sales_point(public_id);

CREATE TRIGGER trg_sales_point_updated_at BEFORE UPDATE ON sales_point
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. Cardinalité point de vente <-> bureau : N par bureau, sans limite
--    ni contrainte (confirmé "très variable selon le bureau", 17/07).
--    Un bureau peut n'avoir aucun point de vente.
-- 2. Aucun rattachement client (party_account) à un point de vente
--    précis -- confirmé explicitement inutile (17/07) : le point de
--    vente ne concerne que la réservation/le paiement, jamais le tiers
--    lui-même.
-- 3. Booking portera, dans une session ultérieure, DEUX FK nullables
--    indépendantes vers cette table : une pour le point de vente de
--    VENTE (où la résa a été prise) et une pour le point de vente de
--    PAIEMENT (où l'argent a été physiquement encaissé) -- confirmés
--    comme deux rôles distincts et potentiellement divergents sur
--    données réelles (hôtel ET maritime). Ce script ne modifie PAS
--    Booking -- ajustement à signaler et faire dans une session dédiée
--    au chat pilote Booking.
-- 4. Aucun recouvrement avec Cash Management : cash_session est scopée
--    par holder_account_id (le caissier), avec office_account_id
--    purement informatif -- aucun lien structurel au point de vente.
--    Le rôle "paiement" du point de vente reste une métadonnée sur la
--    résa, indépendante de la mécanique caisse. Vérifié le 17/07/2026.
-- 5. Testé sur PostgreSQL 16 réel (sandbox) : bootstrap bureau, 2
--    points de vente sur le même bureau (cardinalité N confirmée),
--    trigger updated_at vérifié, index partiel vérifié. Aucune donnée
--    réelle de points de vente disponible à ce jour -- à reconfronter
--    dès qu'un export sera fourni (legacy ost_sales_point).
-- ============================================================
-- FIN
-- ============================================================
