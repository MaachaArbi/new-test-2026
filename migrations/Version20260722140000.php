<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_charge_type + booking_charge (pan financier — charges seules).
 */
final class Version20260722140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_charge_type + booking_charge (SUM totaux = règle applicative)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_charge_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO booking_charge_type (code, sort_order) VALUES
    ('room_rate',                 0),
    ('discount',                  1),
    ('margin_agence_principale',  2),
    ('margin_distributeur',       3),
    ('file_fee',                  4),
    ('fiscal_stamp',              5),
    ('city_tax',                  6),
    ('fare',                      7),
    ('service_fee',               8),
    ('commission',                9),
    ('withholding_tax',          10),
    ('vehicle_transport',        11),
    ('accommodation',            12),
    ('rental_base',               13),
    ('pickup_fee',                14),
    ('dropoff_fee',               15),
    ('supplement',                16),
    ('transfer_fee',              17),
    ('passenger_insurance',       18),
    ('vehicle_insurance',         19),
    ('meal',                      20),
    ('refund',                    21),
    ('other',                    99);

CREATE TABLE booking_charge (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id        BIGINT NOT NULL,
    traveler_id       BIGINT REFERENCES booking_traveler(id),
    segment_id        BIGINT REFERENCES booking_transport_segment(id),
    charge_type_code  VARCHAR(30) NOT NULL REFERENCES booking_charge_type(code),
    label             VARCHAR(255),
    metadata          JSONB NOT NULL DEFAULT '{}'::jsonb,
    achat_amount      BIGINT NOT NULL DEFAULT 0,
    vente_amount      BIGINT NOT NULL DEFAULT 0,
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_charge IS 'Décomposition agrégée du prix. SUM(vente_amount) = booking.total_vente_amount — règle applicative (jamais trigger, ADR-002).';

CREATE INDEX idx_booking_charge_booking ON booking_charge(booking_id, sort_order);
CREATE INDEX idx_booking_charge_traveler ON booking_charge(traveler_id) WHERE traveler_id IS NOT NULL;
CREATE INDEX idx_booking_charge_segment ON booking_charge(segment_id) WHERE segment_id IS NOT NULL;
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
