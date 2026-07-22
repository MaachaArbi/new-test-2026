-- =============================================================================
-- Cash Management V1 — slice référentiel routing (vague initiale backend)
-- =============================================================================
-- Source : modele-conceptuel-cash-management.md (décision #2).
-- Le fichier schema complet n'était pas versionné dans le dépôt au moment
-- de cette vague ; cette slice couvre cash_routing_type +
-- cash_payment_method_routing uniquement (pas session/movement/PL/pgSQL).
-- =============================================================================

CREATE TABLE IF NOT EXISTS cash_routing_type (
    code       VARCHAR(30) PRIMARY KEY,
    label      VARCHAR(80) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_routing_type IS
'Référentiel de destination physique (table, jamais ENUM).
 Seed : caisse / banque_directe / transmission_externe / aucun.';

INSERT INTO cash_routing_type (code, label) VALUES
    ('caisse', 'Caisse'),
    ('banque_directe', 'Banque directe'),
    ('transmission_externe', 'Transmission externe'),
    ('aucun', 'Aucun routing / hors cash')
ON CONFLICT (code) DO NOTHING;

CREATE TABLE IF NOT EXISTS cash_payment_method_routing (
    payment_method_id        BIGINT PRIMARY KEY
                               REFERENCES reglement_payment_method(id),
    routing_type_code        VARCHAR(30) NOT NULL
                               REFERENCES cash_routing_type(code),
    instrument_tracking_mode VARCHAR(20) NOT NULL
                               CHECK (instrument_tracking_mode IN (
                                   'individual', 'aggregate', 'not_applicable'
                               )),
    strict_source_isolation  BOOLEAN NOT NULL DEFAULT false,
    requires_custody_check   BOOLEAN NOT NULL DEFAULT true,
    is_active                BOOLEAN NOT NULL DEFAULT true,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_routing_tracking_consistency CHECK (
        (routing_type_code = 'aucun'
            AND instrument_tracking_mode = 'not_applicable')
        OR
        (routing_type_code <> 'aucun'
            AND instrument_tracking_mode <> 'not_applicable')
    )
);

COMMENT ON TABLE cash_payment_method_routing IS
'Extension 1-1 de reglement_payment_method. Pilote 100% du routing Cash
 Management — aucun code mode (E/C/V…) en dur. Modifiable par UPDATE.';

CREATE TRIGGER trg_cash_payment_method_routing_updated_at
    BEFORE UPDATE ON cash_payment_method_routing
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
