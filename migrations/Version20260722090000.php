<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_transport_segment (flight/train/maritime/transfer).
 */
final class Version20260722090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_transport_segment + index booking/sequence';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_transport_segment (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id       BIGINT NOT NULL,
    sequence_number  SMALLINT NOT NULL DEFAULT 1,
    carrier_code     VARCHAR(50),
    departure_at     TIMESTAMPTZ NOT NULL,
    arrival_at       TIMESTAMPTZ NOT NULL,
    departure_location VARCHAR(100),
    arrival_location    VARCHAR(100),
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_booking_transport_segment_booking ON booking_transport_segment(booking_id, sequence_number);
SQL;

        $this->execSqlFile($this->connection, $sql);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Les migrations SQL de référence ne sont pas réversibles automatiquement.'
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
