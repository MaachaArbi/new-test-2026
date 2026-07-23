-- ============================================================
-- Module         : Core / Identity & Authentication (core_)
-- Objet          : Authentification transverse, découplée du CRM.
--                   Le module Party répond à "qui est ce tiers", ce
--                   module répond à "comment on vérifie que c'est bien lui".
-- Version        : 1.1 - Référence mise à jour vers party_account
--                   (renommé depuis crm_account le 14/07/2026)
-- Date           : 2026-07-14
-- Dépend de      : party_account (module party_, voir schema-party-account-v1.sql)
-- ============================================================

-- Réutilise la fonction set_updated_at() définie dans le module crm_.
-- Si ce module est déployé indépendamment, la (re)créer ici :
-- CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$
-- BEGIN
--     NEW.updated_at = now();
--     RETURN NEW;
-- END;
-- $$ LANGUAGE plpgsql;

-- ============================================================
-- core_credential : authentification multi-provider
-- Un compte peut avoir 0 à N credentials (local, google, facebook, api_key...)
-- Transverse à tous les rôles/personas (internal_user, agence B2B, client
-- B2C, extranet hôtel, compte system...).
-- ============================================================
CREATE TABLE core_credential (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id        BIGINT NOT NULL REFERENCES party_account(id),
    provider          VARCHAR(30) NOT NULL, -- 'local','google','facebook','api_key','sso_interne'
    provider_user_id  VARCHAR(255),         -- NULL si provider = 'local'
    password_hash     VARCHAR(255),         -- NULL si provider != 'local' ; Argon2id recommandé
    is_primary        BOOLEAN NOT NULL DEFAULT false,
    is_enabled        BOOLEAN NOT NULL DEFAULT true,
    last_login_at     TIMESTAMPTZ,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at        TIMESTAMPTZ
);

COMMENT ON TABLE core_credential IS 'Identité d''authentification, découplée du CRM. Les tokens OAuth (access/refresh) ne sont PAS stockés ici : vault/secret manager séparé — seule la référence provider_user_id l''est.';

CREATE UNIQUE INDEX uq_core_credential_provider_identity ON core_credential(provider, provider_user_id)
    WHERE deleted_at IS NULL AND provider_user_id IS NOT NULL;
CREATE INDEX idx_core_credential_account ON core_credential(account_id) WHERE deleted_at IS NULL;

CREATE TRIGGER trg_core_credential_updated_at BEFORE UPDATE ON core_credential
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. Sécurité : password hashé (Argon2id), jamais en clair. is_primary
--    permet de désigner le moyen de connexion privilégié si plusieurs
--    providers coexistent pour un même compte.
-- 2. Restriction "internal_user ne doit utiliser que local/SSO interne" :
--    règle applicative, pas une contrainte de table (garde la flexibilité).
-- 3. Ce module est le point d'ancrage naturel pour un futur système de
--    sessions/tokens JWT, MFA, ou migration vers un IAM externe
--    (Keycloak, Auth0...) sans impact sur le schéma crm_.
