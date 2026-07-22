<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Corrige la slice routing divergente (VARCHAR 30→20 + seed manquant).
 *
 * Base de dev uniquement déjà migrée avec Version20260722200000 divergente.
 * Version20260722200000 a été réécrite pour les installs neuves.
 */
final class Version20260722201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align cash routing columns VARCHAR(20) + seed cash_payment_method_routing';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
ALTER TABLE cash_payment_method_routing
    ALTER COLUMN routing_type_code TYPE VARCHAR(20);
ALTER TABLE cash_routing_type
    ALTER COLUMN code TYPE VARCHAR(20);

UPDATE cash_routing_type SET label = 'Passe par une session de caisse' WHERE code = 'caisse';
UPDATE cash_routing_type SET label = 'Atterrit directement en banque, sans caisse' WHERE code = 'banque_directe';
UPDATE cash_routing_type SET label = 'Transmis physiquement à un tiers émetteur' WHERE code = 'transmission_externe';
UPDATE cash_routing_type SET label = 'Scriptural pur, hors périmètre Cash Management' WHERE code = 'aucun';

TRUNCATE cash_payment_method_routing;

INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'aucun', 'not_applicable', false FROM reglement_payment_method WHERE code IN ('AD','CB');
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'caisse', 'individual', false FROM reglement_payment_method WHERE code IN ('C','LC');
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'caisse', 'individual', true FROM reglement_payment_method WHERE code = 'E';
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'transmission_externe', 'individual', false FROM reglement_payment_method WHERE code = 'PC';
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'banque_directe', 'individual', false FROM reglement_payment_method WHERE code = 'V';
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'banque_directe', 'individual', false FROM reglement_payment_method WHERE code = 'VE';
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'aucun', 'not_applicable', false FROM reglement_payment_method WHERE code IN ('RC','RI');
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
