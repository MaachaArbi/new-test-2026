<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * §67 — Resserrement de uq_cash_movement_instrument_per_session.
 *
 * Les tables cash_deposit / cash_external_transmission sont absentes en
 * runtime : le cycle brouillon/validation vit dans reference/ uniquement.
 * Cet index existant devait toutefois être resserré (encaissements seuls)
 * pour permettre les sorties/contre-passations du même instrument — sinon
 * le §67 est structurellement impossible dès l'import des bordereaux.
 *
 * SQL encadré BEGIN/COMMIT (décision migrations atomiques 24/07).
 */
final class Version20260724160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '§67: narrow uq_cash_movement_instrument_per_session to receipts only';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
BEGIN;

DROP INDEX IF EXISTS uq_cash_movement_instrument_per_session;

CREATE UNIQUE INDEX uq_cash_movement_instrument_per_session
    ON cash_movement(session_id, instrument_id)
    WHERE instrument_id IS NOT NULL
      AND amount_minor > 0
      AND reversal_of_movement_id IS NULL;

COMMIT;
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
