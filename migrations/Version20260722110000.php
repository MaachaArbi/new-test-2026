<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_car_rental_detail (extension 1-1, service_type car_rental).
 */
final class Version20260722110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_car_rental_detail + trigger updated_at';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_car_rental_detail (
    booking_id           BIGINT PRIMARY KEY,
    vehicle_category      VARCHAR(100),
    vehicle_brand_model    VARCHAR(100),
    pickup_at                TIMESTAMPTZ,
    dropoff_at                 TIMESTAMPTZ,
    pickup_location              VARCHAR(255),
    dropoff_location               VARCHAR(255),
    created_at                       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                          TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_car_rental_detail IS 'Extension 1-1 spécifique location de voiture. pickup_at/dropoff_at portent la précision horaire que booking.start_date/end_date (DATE) n''offre pas.';

CREATE TRIGGER trg_booking_car_rental_detail_updated_at BEFORE UPDATE ON booking_car_rental_detail
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
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
