<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice extension hôtel : booking_accommodation_detail + booking_hotel_room.
 *
 * Écart vs schéma figé : accommodation_id BIGINT sans REFERENCES
 * ref_accommodation (module ref_ non importé) — FK applicative uniquement,
 * comme pointvente_* sur booking.
 */
final class Version20260722070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_accommodation_detail + booking_hotel_room (hôtel)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_accommodation_detail (
    booking_id                   BIGINT PRIMARY KEY,
    accommodation_id             BIGINT,
    accommodation_name_snapshot  VARCHAR(255),
    board_type                   VARCHAR(50),
    created_at                   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TRIGGER trg_booking_accommodation_detail_updated_at BEFORE UPDATE ON booking_accommodation_detail
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE booking_hotel_room (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id  BIGINT NOT NULL,
    room_type   VARCHAR(100),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_booking_hotel_room_booking ON booking_hotel_room(booking_id);
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
