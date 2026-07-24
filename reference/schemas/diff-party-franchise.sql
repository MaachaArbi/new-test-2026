-- ============================================================
-- Réouverture ponctuelle documentée — Franchises
-- Additif pur sur party_ : nouveau party_role='franchise', extension
-- 1-1 party_account_franchise (marque qu'un compte est une franchise,
-- même pattern que party_account_office).
-- ============================================================

INSERT INTO party_role (code, sort_order) VALUES ('franchise', 5);

INSERT INTO party_role_translation (role_code, language_code, label) VALUES
    ('franchise', 'en', 'Franchise'),
    ('franchise', 'fr', 'Franchise'),
    ('franchise', 'ar', 'امتياز');

-- ============================================================
-- party_account_franchise : extension 1-1, marque qu'un party_account
-- (nature=organization, party_role='franchise') est une franchise --
-- bureau externe n'appartenant PAS à l'agence principale, acteur
-- économique (grand livre Règlements), rémunéré par commission
-- (calculée par Pricing, rule_nature='commission', déjà existant --
-- rien à dupliquer ici).
-- ============================================================
CREATE TABLE party_account_franchise (
    account_id  BIGINT PRIMARY KEY REFERENCES party_account(id), -- doit être nature='organization' ET porter party_role='franchise' (règle applicative)
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by  BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE party_account_franchise IS 'Marque qu''un party_account est une franchise (bureau externe, acteur économique distinct de party_account_office). La commission est calculée par Pricing (rule_nature=commission), pas stockée ici -- cette table ne fait que qualifier le compte.';

-- ============================================================
-- Règle applicative élargie sur sales_point.office_account_id (AUCUN
-- changement de colonne -- reste BIGINT REFERENCES party_account(id)) :
-- peut désormais référencer soit un compte portant party_account_office
-- (bureau interne), soit un compte portant party_account_franchise
-- (site de paiement d'une franchise externe). Le grand livre reste
-- TOUJOURS porté par le party_account franchise lui-même, jamais par
-- sales_point (qui reste volontairement dépourvu de rôle financier --
-- principe non modifié).
-- ============================================================
COMMENT ON COLUMN sales_point.office_account_id IS 'Référence un party_account portant SOIT party_account_office (bureau interne), SOIT party_account_franchise (site de paiement d''une franchise externe) -- règle applicative élargie le 20/07/2026, aucun rôle financier sur sales_point dans les deux cas.';
