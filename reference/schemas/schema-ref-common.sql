-- ============================================================
-- Module         : Référentiel commun (ref_)
-- Objet          : Langues et devises supportées, partagées par tous les
--                   modules ayant du contenu traduisible ou du multi-devise
--                   (party_, booking_, ref_ (hébergement/géographie),
--                   futurs pricing_...).
-- Version        : 1.2 - Ajout native_name/alpha3/oct_code (ref_language),
--                   numeric_code/symbol/oct_code (ref_currency)
-- Date           : 2026-07-17
-- ============================================================
-- Principe : on ne traduit QUE du contenu (libellés, descriptions),
-- jamais des données métier (noms de sociétés, adresses...). Voir
-- modele-conceptuel-party.md pour la justification.
--
-- Historique — réouverture additive du 17/07/2026 (module Référentiel
-- Hébergement & Géographie) : une seule entité langue/devise pour tout
-- le projet plutôt qu'un doublon avec le référentiel OctaSoft Static
-- Data. Réouverture MINIMALE et documentée : ajout de colonnes
-- uniquement, `code` (PK VARCHAR) INCHANGÉ, aucune table existante
-- référençant ref_language(code)/ref_currency(code) n'a été modifiée
-- (party_role_translation, booking_status_translation, ref_country_
-- translation...). Exception documentée à ADR-018 (comme déjà de facto :
-- pas de BIGINT identity/public_id ici, ces tables restent petites, le
-- gain de performance visé par l'ADR-018 ne s'applique pas). Voir
-- ref-common-hebergement-extension.diff pour le détail exact du patch,
-- et modele-conceptuel-ref-static.md pour la discussion complète.
-- ============================================================

CREATE TABLE ref_language (
    code        VARCHAR(5) PRIMARY KEY,        -- ISO 639-1 : 'en', 'fr', 'ar'...
    label       VARCHAR(100) NOT NULL,         -- libellé technique (anglais), pas destiné à l'UI
    native_name VARCHAR(100),                  -- nom de la langue dans la langue elle-même (ex: "Français"), fourni par OctaSoft Static Data
    alpha3      CHAR(3),                       -- ISO 639-2/639-3 alpha-3, fourni par OctaSoft Static Data
    oct_code    VARCHAR(50) NOT NULL,           -- code de réconciliation OctaSoft Static Data. Aucun ajout local possible pour cette entité.
    is_rtl      BOOLEAN NOT NULL DEFAULT false, -- ex: 'ar' = true (RTL) -- sert aussi de "direction" (pas de colonne dupliquée)
    is_active   BOOLEAN NOT NULL DEFAULT true,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_language_oct_code ON ref_language(oct_code);
CREATE UNIQUE INDEX uq_ref_language_alpha3 ON ref_language(alpha3) WHERE alpha3 IS NOT NULL;

COMMENT ON TABLE ref_language IS 'Langues supportées. EN = langue pivot (fallback par défaut). Extensible sans migration lourde : ajouter une langue = une ligne, pas un ALTER TABLE. is_rtl porte aussi la direction du texte (pas de colonne "direction" séparée). Aucun ajout local possible : oct_code NOT NULL (étendu 17/07, voir historique en tête de fichier).';

INSERT INTO ref_language (code, label, native_name, alpha3, oct_code, is_rtl) VALUES
    ('en', 'English', 'English',  'eng', '1', false),
    ('fr', 'French',  'Français', 'fra', '2', false),
    ('ar', 'Arabic',  'العربية',  'ara', '3', true);

-- ============================================================
-- ref_currency : devises supportées (ISO 4217)
-- minor_unit = nombre de décimales (ex: TND = 3 "millimes", la
-- plupart des autres devises = 2). Pertinent pour la convention
-- "argent en centimes" (00-project_overview.md) : le multiplicateur
-- BIGINT n'est pas toujours x100.
-- ============================================================
CREATE TABLE ref_currency (
    code         VARCHAR(3) PRIMARY KEY,        -- ISO 4217 : 'TND', 'EUR', 'USD', 'DZD'...
    label        VARCHAR(100) NOT NULL,
    numeric_code CHAR(3),                       -- ISO 4217 numérique
    symbol       VARCHAR(10),                   -- symbole d'affichage (ex: "د.ت", "€", "$")
    oct_code     VARCHAR(50) NOT NULL,           -- code de réconciliation OctaSoft Static Data. Aucun ajout local possible pour cette entité.
    minor_unit   SMALLINT NOT NULL DEFAULT 2,   -- nombre de décimales (TND=3, EUR/USD=2)
    is_active    BOOLEAN NOT NULL DEFAULT true,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_ref_currency_oct_code ON ref_currency(oct_code);

COMMENT ON TABLE ref_currency IS 'Devises supportées. minor_unit pilote le multiplicateur réel pour le stockage en BIGINT (ex: TND -> x1000, pas x100). Aucun ajout local possible : oct_code NOT NULL (étendu 17/07, voir historique en tête de fichier). PLACEHOLDER sur le seed initial (voir NOTE ci-dessous) -- à écraser au premier import réel avant production.';

INSERT INTO ref_currency (code, label, numeric_code, symbol, oct_code, minor_unit) VALUES
    ('TND', 'Tunisian Dinar', '788', 'د.ت', 'PLACEHOLDER-TND', 3),
    ('EUR', 'Euro',           '978', '€',   'PLACEHOLDER-EUR', 2),
    ('USD', 'US Dollar',      '840', '$',   'PLACEHOLDER-USD', 2),
    ('DZD', 'Algerian Dinar', '012', 'د.ج', 'PLACEHOLDER-DZD', 2);

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. EN est la langue pivot : fallback applicatif = locale demandée -> en
--    -> première langue active disponible. Pas de contrainte NOT NULL
--    forcée en trigger DB (cohérent avec le choix de ne pas imposer une
--    langue obligatoire au niveau base, décision prise pour rester
--    flexible sur les imports en masse / création programmatique).
-- 2. is_rtl sert au frontend (direction du texte), pas consommé côté DB.
-- 3. oct_code sur ref_currency (seed initial) est un PLACEHOLDER --
--    aucune vraie valeur communiquée au 17/07/2026 ("mets ce que tu
--    veux" -- utilisateur). À REMPLACER PAR LE VRAI PREMIER IMPORT
--    OctaSoft Static Data avant mise en production. numeric_code/symbol
--    sont en revanche des vraies valeurs ISO 4217.
