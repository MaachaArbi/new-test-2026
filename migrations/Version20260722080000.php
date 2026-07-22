<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_traveler (snapshot voyageur) + index pax leader unique.
 */
final class Version20260722080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_traveler (snapshot + uq pax_leader)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_traveler (
    id                      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id              BIGINT NOT NULL,
    hotel_room_id           BIGINT REFERENCES booking_hotel_room(id),
    party_account_id        BIGINT REFERENCES party_account(id),

    first_name              VARCHAR(150) NOT NULL,
    last_name               VARCHAR(150) NOT NULL,
    civility                VARCHAR(10),
    phone                   VARCHAR(50),
    email                   VARCHAR(255),
    age                     SMALLINT,
    birth_date              DATE,
    birth_place             VARCHAR(150),
    nationality_country_id  BIGINT,
    residence_country_id    BIGINT,

    document_type           VARCHAR(30),
    document_number         VARCHAR(50),
    driving_license_number  VARCHAR(50),

    is_pax_leader           BOOLEAN NOT NULL DEFAULT false,

    ticket_number           VARCHAR(50),
    pnr                     VARCHAR(20),
    travel_class            VARCHAR(10),

    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_booking_traveler_pax_leader ON booking_traveler(booking_id) WHERE is_pax_leader = true;
CREATE INDEX idx_booking_traveler_booking ON booking_traveler(booking_id);
CREATE INDEX idx_booking_traveler_hotel_room ON booking_traveler(hotel_room_id) WHERE hotel_room_id IS NOT NULL;
CREATE INDEX idx_booking_traveler_account ON booking_traveler(party_account_id) WHERE party_account_id IS NOT NULL;
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
