<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Cash Management — cash_movement_type + cash_movement + guard + cash_receive_instrument.
 *
 * Aligné sur schema-cash-management-v1.sql (§2, §4, cash_receive_instrument).
 * cash_session déjà migré (Version20260723000000) — non repris.
 */
final class Version20260723130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import cash_movement_type + cash_movement + guard + cash_receive_instrument()';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE cash_movement_type (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code         VARCHAR(40) NOT NULL UNIQUE,
    label        VARCHAR(150) NOT NULL,
    normal_sign  VARCHAR(1) NOT NULL CHECK (normal_sign IN ('C','D')),  -- informatif, pas contraint sur amount_minor
    is_system    BOOLEAN NOT NULL DEFAULT false,  -- généré uniquement par une fonction de posting
    is_active    BOOLEAN NOT NULL DEFAULT true,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_movement_type IS
'Table de référence, jamais ENUM (cohérent avec reglement_payment_method).
 normal_sign est informatif pour le reporting ; amount_minor sur cash_movement
 est toujours signé et fait foi (une contre-passation peut porter un type
 "normalement crédit" avec un montant négatif).';

INSERT INTO cash_movement_type (code, label, normal_sign, is_system) VALUES
    ('encaissement_instrument',    'Encaissement lié à une pièce de règlement', 'C', true),
    ('decaissement_fournisseur',   'Paiement fournisseur en espèces',           'D', true),
    ('mouvement_libre_credit',     'Mouvement libre - crédit',                  'C', false),
    ('mouvement_libre_debit',      'Mouvement libre - débit',                   'D', false),
    ('transfert_sortant',          'Transfert vers une autre session',          'D', true),
    ('transfert_entrant',          'Transfert reçu d''une autre session',       'C', true),
    ('conversion_sortante',        'Sortie devise convertie',                   'D', true),
    ('conversion_entrante',        'Entrée devise convertie',                   'C', true),
    ('sortie_depot_banque',        'Sortie caisse pour dépôt en banque',        'D', true),
    ('sortie_transmission_externe','Sortie caisse pour transmission externe',   'D', true),
    ('sortie_validation_session',  'Sortie - validation caissier central',      'D', true),
    ('entree_validation_session',  'Entrée caissier central - validation',      'C', true),
    ('ecart_cloture',              'Écart de clôture (signe variable)',         'C', true),
    ('sortie_instrument_retourne', 'Sortie - pièce retournée impayée',          'D', true),
    ('correction_generique',       'Correction / contre-passation générique',   'C', true);

CREATE TABLE cash_movement (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                UUID NOT NULL DEFAULT gen_random_uuid(),

    session_id               BIGINT NOT NULL REFERENCES cash_session(id),
    movement_type_id         BIGINT NOT NULL REFERENCES cash_movement_type(id),

    currency_code             VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    -- SIGNÉ : positif = entrée, négatif = sortie. Jamais 0.
    amount_minor               BIGINT NOT NULL CHECK (amount_minor <> 0),

    -- NULL = mouvement libre/interne (frais, transfert, conversion, écart).
    instrument_id                BIGINT REFERENCES reglement_instrument(id),

    effective_date                 DATE NOT NULL DEFAULT CURRENT_DATE,
    memo                              TEXT,
    reference                          VARCHAR(100),

    reversal_of_movement_id              BIGINT REFERENCES cash_movement(id),

    created_at                             TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                               BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE cash_movement IS
'Journal append-only des sessions. Chaque mouvement porte sa devise propre
 (une session peut encaisser plusieurs devises). Toujours créé via une
 fonction de posting (cash_receive_instrument, cash_post_outflow,
 cash_post_transfer, cash_post_conversion...), jamais d''INSERT direct.';

CREATE INDEX idx_cash_movement_session ON cash_movement(session_id, currency_code, effective_date, id);
CREATE INDEX idx_cash_movement_instrument ON cash_movement(instrument_id) WHERE instrument_id IS NOT NULL;
CREATE UNIQUE INDEX uq_cash_movement_public_id ON cash_movement(public_id);

-- Invariant structurel (réouverture 23/07/2026, retour chat Backend) : un même
-- reglement_instrument ne peut être encaissé deux fois dans LA MÊME session --
-- jamais légitime (doublon de saisie). Volontairement scopé à session_id : un
-- même instrument peut réapparaître dans une session DIFFÉRENTE (ex. migration
-- chèque agent -> caissier central -> banque via cash_validate_session), ce
-- n'est PAS un doublon, c'est un mouvement de vie légitime -- pas encore
-- construite côté backend, donc aucune contrainte cross-session ici par
-- construction (problème ouvert pour la vague cash_validate_session, voir
-- sujets-reportes.md). Même pattern que uq_cash_session_one_open_per_holder :
-- invariant porté par la base, pas par l'Application.
CREATE UNIQUE INDEX uq_cash_movement_instrument_per_session ON cash_movement(session_id, instrument_id) WHERE instrument_id IS NOT NULL;

CREATE OR REPLACE FUNCTION cash_movement_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    IF TG_OP IN ('UPDATE','DELETE') THEN
        RAISE EXCEPTION 'cash_movement est append-only : UPDATE/DELETE interdits (id=%)', OLD.id;
    END IF;

    SELECT status_code INTO v_status FROM cash_session WHERE id = NEW.session_id FOR UPDATE;
    IF v_status IS NULL THEN
        RAISE EXCEPTION 'Session % introuvable', NEW.session_id;
    ELSIF v_status = 'validated' THEN
        RAISE EXCEPTION 'Session % déjà validée (figée) : une correction doit être postée dans la session ouverte courante, jamais ici.', NEW.session_id;
    END IF;
    -- 'closed' reste inscriptible UNIQUEMENT pour les écritures de
    -- validation du caissier central (cash_validate_session) : c'est l'état
    -- transitoire "fermée par l'utilisateur, en attente de validation".
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_cash_movement_guard
    BEFORE INSERT OR UPDATE OR DELETE ON cash_movement
    FOR EACH ROW EXECUTE FUNCTION cash_movement_guard();

CREATE OR REPLACE FUNCTION cash_receive_instrument(p_session_id BIGINT, p_instrument_id BIGINT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_type_id BIGINT; v_currency VARCHAR(3); v_amount BIGINT; v_movement_id BIGINT;
BEGIN
    SELECT currency_code, amount_minor INTO v_currency, v_amount
    FROM reglement_instrument WHERE id = p_instrument_id;
    IF v_currency IS NULL THEN
        RAISE EXCEPTION 'Instrument % introuvable', p_instrument_id;
    END IF;

    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'encaissement_instrument';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, created_by)
    VALUES (p_session_id, v_type_id, v_currency, v_amount, p_instrument_id, p_by)
    RETURNING id INTO v_movement_id;
    RETURN v_movement_id;
END; $$;

COMMENT ON FUNCTION cash_receive_instrument IS
'Encaissement d''une pièce Règlements dans la session. À appeler uniquement
 si cash_payment_method_routing du mode de règlement de l''instrument a
 routing_type_code=''caisse''.';

SQL;

        $this->execSqlFile($this->connection, $sql);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Les migrations SQL de référence ne sont pas réversibles automatiquement.',
        );
    }

    private function execSqlFile(Connection $connection, string $sql): void
    {
        $native = $connection->getNativeConnection();
        if (!$native instanceof \PDO) {
            throw new RuntimeException('Connexion PDO native attendue pour exécuter le SQL multi-statements.');
        }

        $native->exec($sql);
    }
}
