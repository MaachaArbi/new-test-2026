<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice référentiel data-driven booking_service_extension +
 * booking_service_type_extension (fidèle schema-booking-v1.sql).
 */
final class Version20260722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_service_extension + booking_service_type_extension (seed 3+6)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_service_extension (
    code        VARCHAR(30) PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_service_extension IS 'Référentiel des extensions structurelles de booking (accommodation, transport_segment, car_rental...). Source de vérité pour AssertBookingServiceType — pas de liste PHP figée.';

INSERT INTO booking_service_extension (code, label) VALUES
    ('accommodation',     'Accommodation detail / hotel rooms'),
    ('transport_segment', 'Transport segments'),
    ('car_rental',        'Car rental detail');

CREATE TABLE booking_service_type_extension (
    service_type_code  VARCHAR(30) NOT NULL REFERENCES booking_service_type(code),
    extension_code     VARCHAR(30) NOT NULL REFERENCES booking_service_extension(code),
    PRIMARY KEY (service_type_code, extension_code)
);

COMMENT ON TABLE booking_service_type_extension IS 'Mapping N-N service_type ↔ extension. Ajouter une ligne (ex: bus → transport_segment) active le comportement sans toucher au code PHP.';

INSERT INTO booking_service_type_extension (service_type_code, extension_code) VALUES
    ('hotel',      'accommodation'),
    ('flight',     'transport_segment'),
    ('train',      'transport_segment'),
    ('maritime',   'transport_segment'),
    ('transfer',   'transport_segment'),
    ('car_rental', 'car_rental');
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
