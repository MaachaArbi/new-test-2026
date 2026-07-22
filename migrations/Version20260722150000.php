<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_settlement (faits de répartition — sans recalcul Booking).
 */
final class Version20260722150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_settlement (historisé, uq active sur triplet)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS booking_settlement (
    id                     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id             BIGINT NOT NULL,
    beneficiary_account_id BIGINT NOT NULL REFERENCES party_account(id),
    beneficiary_role       VARCHAR(30) NOT NULL
                               CHECK (beneficiary_role IN ('fournisseur', 'agence_principale', 'distributeur')),
    amount_owed            BIGINT NOT NULL,
    amount_settled_direct  BIGINT NOT NULL DEFAULT 0,
    rate                    NUMERIC(6,3),
    resale_price_amount     BIGINT,
    currency_code          VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    valid_from              TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to                TIMESTAMPTZ,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE booking_settlement IS 'Faits de règlement par bénéficiaire (fournisseur/agence principale/distributeur). Le futur Cash Management calcule "à verser réellement = amount_owed - amount_settled_direct" par bénéficiaire, sans recalculer l''historique. Booking ne génère aucune échéance.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_booking_settlement_active
    ON booking_settlement(booking_id, beneficiary_role, beneficiary_account_id) WHERE valid_to IS NULL;
CREATE INDEX IF NOT EXISTS idx_booking_settlement_booking ON booking_settlement(booking_id) WHERE valid_to IS NULL;
CREATE INDEX IF NOT EXISTS idx_booking_settlement_beneficiary ON booking_settlement(beneficiary_account_id) WHERE valid_to IS NULL;
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
