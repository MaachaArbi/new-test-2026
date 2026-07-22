<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_payer_split (répartition payeurs — plafond Application).
 */
final class Version20260722160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_payer_split (historisé, uq active booking+payer)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS booking_payer_split (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id        BIGINT NOT NULL,
    payer_account_id  BIGINT NOT NULL REFERENCES party_account(id),
    amount            BIGINT NOT NULL,
    valid_from        TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to          TIMESTAMPTZ,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by        BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_payer_split IS 'Répartition du montant à payer entre plusieurs payeurs (ex: amicale/employé), par montant fixe, historisée. SUM(amount) des lignes actives d''un booking ne doit jamais dépasser booking.total_vente_amount (règle applicative, pas une contrainte SQL — égalité stricte non exigée).';

CREATE UNIQUE INDEX IF NOT EXISTS uq_booking_payer_split_active
    ON booking_payer_split(booking_id, payer_account_id) WHERE valid_to IS NULL;
CREATE INDEX IF NOT EXISTS idx_booking_payer_split_booking ON booking_payer_split(booking_id) WHERE valid_to IS NULL;
CREATE INDEX IF NOT EXISTS idx_booking_payer_split_payer ON booking_payer_split(payer_account_id) WHERE valid_to IS NULL;
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
