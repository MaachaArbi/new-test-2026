-- ============================================================================
-- Module      : Log (log_) — transverse
-- Objet       : Journal métier lisible (log_activity) et traçabilité
--               technique/sécurité au niveau ligne (log_audit).
--               Généralise booking_log (Booking, sujets-reportes.md §19),
--               déclenché par 3 signaux réels : Booking, Provider Integration
--               (module à venir), pricing_rule_log (Pricing, motif similaire
--               mais resté local — voir modele-conceptuel-log.md).
-- Version     : 1.0
-- Date        : 20 juillet 2026
-- Ordre       : 5ème script à exécuter (après party_, avant/indépendant de
--               booking_ — booking_ référence log_entity_type mais aucune FK
--               SQL réelle vers log_activity/log_audit, voir note 1)
-- Dépend de   : schema-party-account-v1.sql (party_account, set_updated_at()
--               si jamais réutilisée — non nécessaire ici, table append-only)
-- Réfs        : ADR-006 (audit classique, jamais construit avant cette
--               session — voir sujets-reportes.md §48 point 2), ADR-002
--               (logique métier hors DB — SAUF le trigger d'audit lui-même,
--               qui est un cas assumé et documenté d'exception, voir note 2)
-- ============================================================================

-- ============================================================
-- TABLE DE RÉFÉRENCE : log_entity_type
-- PAS de VARCHAR libre sur entity_type des tables de log (principe
-- anti-ENUM du projet). Porte aussi la politique de rétention, par
-- entité, distincte entre activité et audit (une entité peut vouloir
-- garder son journal d'activité indéfiniment tout en purgeant son
-- audit technique après N jours pour le volume).
-- ============================================================
CREATE TABLE log_entity_type (
    code                      VARCHAR(30) PRIMARY KEY,
    label                     VARCHAR(100) NOT NULL,
    activity_retention_days   INT, -- NULL = conservé indéfiniment
    audit_retention_days      INT, -- NULL = conservé indéfiniment
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE log_entity_type IS 'Référentiel des types d''entités journalisées. Rétention configurable par entité et par nature de log (activité vs audit) -- décision de session (sujets-reportes.md §19 point 8) : mécanisme générique posé ici plutôt que dupliqué par module. core_auth_attempt reste hors de ce mécanisme (rétention courte déjà gérée localement, table volontairement séparée -- voir note 3).';

INSERT INTO log_entity_type (code, label, activity_retention_days, audit_retention_days) VALUES
    ('booking', 'Réservation', NULL, 365);
-- Autres entity_type ajoutés au fil de l'eau par les modules qui en ont besoin
-- (ex: 'invoice' pour Facturation, 'provider_call' pour Provider Integration) --
-- table de référence, extensible sans migration structurelle.

-- ============================================================
-- log_event_type : types d'événement du journal métier (modèle B)
-- Symétrique de log_entity_type. Le texte lisible vit dans
-- log_activity.description ; event_type sert au filtrage.
-- Liste séparée de pricing_log_event_type (décision utilisateur 24/07).
-- ============================================================
CREATE TABLE log_event_type (
    code        VARCHAR(30) PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE log_event_type IS 'Types d''événement de log_activity (filtrage). Extensible sans migration structurelle. Distinct de pricing_log_event_type.';

INSERT INTO log_event_type (code, label, sort_order) VALUES
    ('created',                   'Created',                   0),
    ('status_change',             'Status change',             1),
    ('processing_status_change',  'Processing status change',  2),
    ('notification_supplier',     'Supplier notification',     3),
    ('notification_client',       'Customer notification',     4),
    ('payment',                   'Payment',                   5),
    ('loyalty_points',            'Loyalty points',            6);

-- ============================================================
-- log_activity : journal métier lisible, visible côté client/agent
-- ("qui a fait quoi"). Peuplé EXPLICITEMENT par le code applicatif
-- Symfony -- jamais par un trigger. Remplace booking_log tel quel :
-- mêmes colonnes event_type/description/metadata/actor/ip, portée
-- élargie à toute entité via entity_type/entity_id.
-- ============================================================
CREATE TABLE log_activity (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    entity_type       VARCHAR(30) NOT NULL REFERENCES log_entity_type(code),
    entity_id         BIGINT NOT NULL, -- FK applicative -- pas de contrainte SQL possible vers N tables cibles différentes selon entity_type
    event_type        VARCHAR(30) NOT NULL REFERENCES log_event_type(code),
    description       TEXT NOT NULL,        -- texte formaté à l'écriture, lisible tel quel
    metadata          JSONB NOT NULL DEFAULT '{}'::jsonb, -- ex: {"destinataires": [...], "montant": ..., "status_code_snapshot": "confirmed"}
    actor_account_id  BIGINT REFERENCES party_account(id), -- NULL si système/automatique
    ip_address        INET,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE log_activity IS 'Journal métier unifié, transverse (remplace booking_log). Append-only, jamais modifié. status_code_snapshot (ex-colonne dédiée sur booking_log) vit désormais dans metadata -- {"status_code_snapshot": "..."} -- comportement préservé à l''identique côté applicatif, juste déplacé (sujets-reportes.md §19 point 2).';

CREATE INDEX idx_log_activity_entity ON log_activity(entity_type, entity_id, created_at DESC);
CREATE INDEX idx_log_activity_event_type ON log_activity(event_type);

-- ============================================================
-- log_audit : traçabilité technique/sécurité au niveau ligne
-- (avant/après). Peuplée par un TRIGGER GÉNÉRIQUE réutilisable posé
-- sur les tables critiques -- ADR-006, décidée sur papier il y a 6
-- mois, jamais construite avant cette session (sujets-reportes.md
-- §48 point 2). Ne JAMAIS confondre avec log_activity : même forme
-- structurelle, finalité totalement différente (log_activity = ce que
-- l'équipe/le client doit pouvoir lire ; log_audit = preuve technique
-- brute, colonne par colonne, pour investigation/conformité).
-- ============================================================
CREATE TABLE log_audit (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    entity_type       VARCHAR(30) NOT NULL REFERENCES log_entity_type(code),
    entity_id         BIGINT NOT NULL,
    table_name        VARCHAR(100) NOT NULL, -- nom physique de la table Postgres modifiée -- un entity_type peut recouvrir plusieurs tables (ex: 'booking' -> booking, booking_charge, booking_traveler...). ATTENTION : sur une table partitionnée, c'est le nom de la PARTITION (ex: 'booking_y2026m07'), pas la table logique -- voir note sur log_audit_trigger()
    operation         VARCHAR(10) NOT NULL CHECK (operation IN ('INSERT', 'UPDATE', 'DELETE')),
    before_data       JSONB, -- NULL si INSERT
    after_data        JSONB, -- NULL si DELETE
    actor_account_id  BIGINT REFERENCES party_account(id), -- lu depuis current_setting('app.current_user_id', true) -- voir note 2, NULL si non positionné (migration/script technique)
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE log_audit IS 'Traçabilité technique ligne par ligne (ADR-006). Alimentée exclusivement par log_audit_trigger(), jamais par le code applicatif directement. Append-only.';

CREATE INDEX idx_log_audit_entity ON log_audit(entity_type, entity_id, created_at DESC);
CREATE INDEX idx_log_audit_table ON log_audit(table_name, created_at DESC);

-- ============================================================
-- log_audit_trigger() : fonction générique réutilisable.
-- Usage : CREATE TRIGGER trg_<table>_audit AFTER INSERT OR UPDATE OR
--         DELETE ON <table> FOR EACH ROW
--         EXECUTE FUNCTION log_audit_trigger('<entity_type_code>');
-- L'acteur est lu depuis une variable de session que l'Application
-- (Symfony) doit positionner en tout début de transaction :
--   SET LOCAL app.current_user_id = '<party_account_id>';
-- Absence de positionnement = actor_account_id NULL (scripts internes,
-- migrations) -- ne bloque jamais l'écriture (LEFT JOIN implicite via
-- current_setting(..., true), pas d'exception si absent).
-- Compatible tables partitionnées (PostgreSQL 11+ : un trigger ROW posé
-- sur la table mère s'applique automatiquement à toutes les partitions).
-- ATTENTION (vérifié en sandbox) : sur une table partitionnée, TG_TABLE_NAME
-- retourne le nom de la PARTITION physique (ex: 'booking_y2026m07'), pas le
-- nom logique 'booking' -- donc table_name se fragmente par partition pour
-- booking. Toute requête de reporting sur log_audit groupant par table_name
-- doit normaliser ce préfixe côté Application (ex: LIKE 'booking_%') plutôt
-- que de s'attendre à une valeur unique 'booking'.
-- ============================================================
CREATE OR REPLACE FUNCTION log_audit_trigger() RETURNS TRIGGER AS $$
DECLARE
    v_entity_type  VARCHAR(30) := TG_ARGV[0];
    v_entity_id    BIGINT;
    v_actor        BIGINT;
BEGIN
    v_actor := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    IF TG_OP = 'DELETE' THEN
        v_entity_id := OLD.id;
        INSERT INTO log_audit (entity_type, entity_id, table_name, operation, before_data, after_data, actor_account_id)
        VALUES (v_entity_type, v_entity_id, TG_TABLE_NAME, TG_OP, to_jsonb(OLD), NULL, v_actor);
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        v_entity_id := NEW.id;
        INSERT INTO log_audit (entity_type, entity_id, table_name, operation, before_data, after_data, actor_account_id)
        VALUES (v_entity_type, v_entity_id, TG_TABLE_NAME, TG_OP, to_jsonb(OLD), to_jsonb(NEW), v_actor);
        RETURN NEW;
    ELSE -- INSERT
        v_entity_id := NEW.id;
        INSERT INTO log_audit (entity_type, entity_id, table_name, operation, before_data, after_data, actor_account_id)
        VALUES (v_entity_type, v_entity_id, TG_TABLE_NAME, TG_OP, NULL, to_jsonb(NEW), v_actor);
        RETURN NEW;
    END IF;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION log_audit_trigger() IS 'Trigger générique ADR-006. Un seul argument (entity_type_code) à la pose. Ne PAS écrire de logique métier ici au-delà de la capture avant/après -- toute interprétation reste côté Application (cohérent ADR-002, exception assumée : ce trigger fait de la capture technique, pas du calcul métier).';

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. entity_id est une FK APPLICATIVE (pas de contrainte SQL) : une
--    même colonne entity_id, selon entity_type, référence booking.id,
--    invoice.id, etc. -- des tables différentes, dont certaines
--    partitionnées (booking, PK composite). Impossible d'exprimer une
--    FK SQL polymorphe -- intégrité garantie côté Application, même
--    principe déjà appliqué aux FK filles de booking_ (voir
--    modele-conceptuel-booking.md).
-- 2. log_audit_trigger() est une exception documentée à ADR-002 (logique
--    métier hors DB) : il ne fait AUCUN calcul, AUCUNE règle métier,
--    uniquement une capture mécanique avant/après -- équivalent à un
--    log système, pas à une décision. ADR-006 l'autorise explicitement
--    pour ce cas précis.
-- 3. core_auth_attempt (module Permissions/Franchises/Config, figé) reste
--    VOLONTAIREMENT séparée de log_activity/log_audit -- isole le bruit
--    d'un brute-force avec sa propre rétention courte, déjà gérée
--    localement. Ne jamais fusionner (sujets-reportes.md §19 point 7).
-- 4. Rétention (log_entity_type.activity_retention_days/audit_retention_days)
--    : mécanisme de PURGE non implémenté dans ce script (pas de job/CRON
--    en DB, cohérent ADR-002) -- seulement la configuration. Le job de
--    purge périodique est un sujet Application, à construire quand le
--    volume le justifiera.
-- 5. Pas de fonction set_updated_at()/colonne updated_at sur log_activity
--    ni log_audit : append-only par nature, une ligne de log ne se
--    modifie jamais après écriture.
-- ============================================================
