<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Rattrapage cash_session_balance + trigger (omis de Version20260723130000).
 *
 * Aligné schema-cash-management-v1.sql §5 (lignes 272-305) + backfill depuis cash_movement.
 * Hors périmètre : cash_count_session_currency / cash_validate_session.
 */
final class Version20260723140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import cash_session_balance + refresh trigger + backfill from cash_movement';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
-- ============================================================
-- 5. SOLDE DE SESSION — snapshot maintenu en continu (pattern reglement_balance)
-- ============================================================

CREATE TABLE cash_session_balance (
    session_id        BIGINT NOT NULL REFERENCES cash_session(id),
    currency_code       VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    balance_minor         BIGINT NOT NULL DEFAULT 0,
    last_movement_id        BIGINT,
    movement_count             INT NOT NULL DEFAULT 0,
    updated_at                   TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (session_id, currency_code)
);

COMMENT ON TABLE cash_session_balance IS
'Snapshot O(sessions x devises), jamais recalculé par lecture du journal.
 Permet de vérifier une session ouverte depuis un mois sans rejouer l''historique.';

CREATE OR REPLACE FUNCTION cash_session_balance_refresh() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO cash_session_balance (session_id, currency_code, balance_minor, last_movement_id, movement_count, updated_at)
    VALUES (NEW.session_id, NEW.currency_code, NEW.amount_minor, NEW.id, 1, now())
    ON CONFLICT (session_id, currency_code) DO UPDATE
    SET balance_minor    = cash_session_balance.balance_minor + NEW.amount_minor,
        last_movement_id = NEW.id,
        movement_count   = cash_session_balance.movement_count + 1,
        updated_at       = now();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_cash_movement_balance_refresh
    AFTER INSERT ON cash_movement
    FOR EACH ROW EXECUTE FUNCTION cash_session_balance_refresh();


INSERT INTO cash_session_balance (session_id, currency_code, balance_minor, last_movement_id, movement_count, updated_at)
SELECT session_id, currency_code, SUM(amount_minor),
       (ARRAY_AGG(id ORDER BY id DESC))[1], COUNT(*), now()
FROM cash_movement
GROUP BY session_id, currency_code;

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
