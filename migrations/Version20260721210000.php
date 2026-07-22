<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Première tranche Booking : table booking_folder uniquement
 * (schema-booking-v1.sql L243-266). Pivot booking (partitionné) reporté.
 */
final class Version20260721210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_folder (slice schema-booking-v1.sql) — pivot booking reporté';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_folder (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),

    reference_code     VARCHAR(30) NOT NULL,
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    office_account_id  BIGINT NOT NULL REFERENCES party_account(id),

    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    deleted_at         TIMESTAMPTZ,
    created_by         BIGINT REFERENCES party_account(id),
    updated_by         BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_folder IS 'Dossier de réservations. Un dossier = un client porteur (party_account_id) ; le cas rare de partage entre plusieurs clients se gère par réservation via booking_payer_split, pas ici. Un "voyage organisé" est simplement un dossier avec plusieurs booking, sans entité package dédiée (aucun cas concret ne l''exige à ce jour).';

CREATE UNIQUE INDEX uq_booking_folder_public_id ON booking_folder(public_id);
CREATE UNIQUE INDEX uq_booking_folder_reference_code ON booking_folder(reference_code);
CREATE INDEX idx_booking_folder_account ON booking_folder(party_account_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_booking_folder_office ON booking_folder(office_account_id) WHERE deleted_at IS NULL;

CREATE TRIGGER trg_booking_folder_updated_at BEFORE UPDATE ON booking_folder
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
