<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Ajoute sort_order sur booking_service_extension (décision pilote DB 23/07).
 *
 * Ne modifie pas Version20260722100000 (déjà appliquée).
 */
final class Version20260723120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sort_order to booking_service_extension';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
ALTER TABLE booking_service_extension ADD COLUMN sort_order SMALLINT NOT NULL DEFAULT 0;

UPDATE booking_service_extension SET sort_order = 0 WHERE code = 'accommodation';
UPDATE booking_service_extension SET sort_order = 1 WHERE code = 'transport_segment';
UPDATE booking_service_extension SET sort_order = 2 WHERE code = 'car_rental';
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
