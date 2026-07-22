<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Cash Management — référentiel routing uniquement.
 *
 * Hors périmètre : cash_session, cash_movement, fonctions PL/pgSQL.
 * Seed routing_type : 4 lignes. Pas de seed cash_payment_method_routing
 * (dépend des payment_method_id déjà en base — suite logique documentée).
 */
final class Version20260722200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import cash_routing_type (seed) + cash_payment_method_routing (structure)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
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
