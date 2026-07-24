<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Applique les 7 décisions de l'audit des valeurs par défaut (pilote DB, 24/07).
 *
 * En base réelle (tables déjà migrées) :
 *  - booking.channel_code DROP DEFAULT
 *  - party_account_address.address_type DROP DEFAULT
 *  - ref_currency.minor_unit DROP DEFAULT
 *  - cash_payment_method_routing.instrument_tracking_mode DROP DEFAULT (idempotent :
 *    déjà absent en runtime depuis l'alignement 22/07)
 *
 * Hors migration (tables absentes en runtime — reference/ uniquement) :
 *  - booking_payment.status
 *  - core_permission.is_delegable
 *  - config_application_setting.mfa_issuer_name + triggers 2FA
 */
final class Version20260724120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Apply 7 default-value audit decisions (drop/alter DEFAULT)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
BEGIN;

-- 1. booking.channel_code — parent partitionné (propagation auto aux partitions)
ALTER TABLE booking ALTER COLUMN channel_code DROP DEFAULT;

-- 3. cash_payment_method_routing.instrument_tracking_mode (idempotent)
ALTER TABLE cash_payment_method_routing ALTER COLUMN instrument_tracking_mode DROP DEFAULT;

-- 4. party_account_address.address_type
ALTER TABLE party_account_address ALTER COLUMN address_type DROP DEFAULT;

-- 5. ref_currency.minor_unit (seeds TND=3 / EUR=2 / USD=2 / DZD=2 inchangés)
ALTER TABLE ref_currency ALTER COLUMN minor_unit DROP DEFAULT;

COMMIT;
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
