<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * §8 — Suppression de booking_default (vide) + enregistrement pg_partman.
 *
 * Les tables core_session / core_auth_attempt / provider_call_log sont
 * absentes en runtime : leur enregistrement partman se fera via
 * docker/postgres/sql/pg_partman_setup.sql après import de la chaîne.
 *
 * SQL encadré BEGIN/COMMIT (décision migrations atomiques 24/07), sauf
 * CREATE EXTENSION qui reste hors transaction (exigence PostgreSQL).
 */
final class Version20260724170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '§8: drop booking_default + register booking with pg_partman';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->execSqlFile(
            $this->connection,
            <<<'SQL'
CREATE SCHEMA IF NOT EXISTS partman;
CREATE EXTENSION IF NOT EXISTS pg_partman WITH SCHEMA partman;
SQL
        );

        $this->execSqlFile(
            $this->connection,
            <<<'SQL'
BEGIN;

DO $$
DECLARE
    v_count bigint;
BEGIN
    IF to_regclass('public.booking_default') IS NOT NULL THEN
        SELECT count(*) INTO v_count FROM booking_default;
        IF v_count > 0 THEN
            RAISE EXCEPTION
                'booking_default contient % ligne(s) — réattribuer avant DROP (§8)',
                v_count;
        END IF;
        DROP TABLE booking_default;
    END IF;

    -- Aligner le bootstrap historique sur la convention de nommage pg_partman
    IF to_regclass('public.booking_y2026m07') IS NOT NULL
       AND to_regclass('public.booking_p20260701') IS NULL THEN
        ALTER TABLE booking_y2026m07 RENAME TO booking_p20260701;
    END IF;
    IF to_regclass('public.booking_y2026m08') IS NOT NULL
       AND to_regclass('public.booking_p20260801') IS NULL THEN
        ALTER TABLE booking_y2026m08 RENAME TO booking_p20260801;
    END IF;
    IF to_regclass('public.booking_y2026m09') IS NOT NULL
       AND to_regclass('public.booking_p20260901') IS NULL THEN
        ALTER TABLE booking_y2026m09 RENAME TO booking_p20260901;
    END IF;
END $$;

DO $$
DECLARE
    v_managed boolean;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM partman.part_config WHERE parent_table = 'public.booking'
    ) INTO v_managed;

    IF NOT v_managed AND to_regclass('public.booking') IS NOT NULL THEN
        PERFORM partman.create_parent(
            p_parent_table := 'public.booking',
            p_control := 'booking_date',
            p_interval := '1 month',
            p_type := 'range',
            p_premake := 3,
            p_default_table := false,
            p_automatic_maintenance := 'on',
            p_jobmon := false
        );
    END IF;

    UPDATE partman.part_config
    SET premake = 3,
        infinite_time_partitions = true,
        retention = NULL,
        retention_keep_table = true,
        retention_keep_index = true
    WHERE parent_table = 'public.booking';
END $$;

SELECT partman.run_maintenance(p_jobmon := false);

COMMIT;
SQL
        );
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
