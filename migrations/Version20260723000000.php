<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Cash Management — cash_session + open/close uniquement.
 *
 * Aligné sur schema-cash-management-v1.sql (§3 + cash_open_session + cash_close_session).
 * Hors périmètre : cash_movement, balances, validation, autres fonctions PL/pgSQL.
 */
final class Version20260723000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import cash_session + cash_open_session() + cash_close_session()';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE cash_session (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id           UUID NOT NULL DEFAULT gen_random_uuid(),

    holder_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    office_account_id   BIGINT REFERENCES party_account(id),

    status_code         VARCHAR(20) NOT NULL DEFAULT 'open'
                           CHECK (status_code IN ('open','closed','validated')),

    opened_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    opened_by           BIGINT REFERENCES party_account(id),

    closed_at           TIMESTAMPTZ,
    closed_by            BIGINT REFERENCES party_account(id),

    validated_at         TIMESTAMPTZ,
    validated_by          BIGINT REFERENCES party_account(id),

    created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_session_lifecycle CHECK (
        (status_code = 'open'      AND closed_at IS NULL     AND validated_at IS NULL)
        OR (status_code = 'closed'    AND closed_at IS NOT NULL AND validated_at IS NULL)
        OR (status_code = 'validated' AND closed_at IS NOT NULL AND validated_at IS NOT NULL)
    )
);

COMMENT ON TABLE cash_session IS
'La caisse EST la session (pas d''entité caisse persistante séparée). Le fond
 de caisse ne persiste PAS automatiquement d''une session à l''autre du même
 utilisateur (décision actée, conforme au principe legacy "enveloppe" —
 l''argent retourne au caissier central à chaque validation). Une session
 validated pèse zéro par construction (voir cash_validate_session).';

CREATE UNIQUE INDEX uq_cash_session_one_open_per_holder ON cash_session(holder_account_id) WHERE status_code = 'open';
CREATE INDEX idx_cash_session_office ON cash_session(office_account_id);
CREATE UNIQUE INDEX uq_cash_session_public_id ON cash_session(public_id);

CREATE OR REPLACE FUNCTION cash_open_session(p_holder_account_id BIGINT, p_office_account_id BIGINT, p_opened_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE v_id BIGINT;
BEGIN
    INSERT INTO cash_session (holder_account_id, office_account_id, opened_by)
    VALUES (p_holder_account_id, p_office_account_id, p_opened_by)
    RETURNING id INTO v_id;
    RETURN v_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_close_session(p_session_id BIGINT, p_closed_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    UPDATE cash_session SET status_code = 'closed', closed_at = now(), closed_by = p_closed_by
    WHERE id = p_session_id AND status_code = 'open';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Session % introuvable ou déjà fermée', p_session_id;
    END IF;
END; $$;
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
