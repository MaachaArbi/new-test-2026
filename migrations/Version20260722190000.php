<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Règlements — lettrage N-N optionnel (ne touche pas le solde).
 */
final class Version20260722190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import reglement_matching (lettrage soft-unmatchable)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS reglement_matching (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id             UUID NOT NULL DEFAULT gen_random_uuid(),
    debit_entry_id        BIGINT NOT NULL REFERENCES reglement_ledger_entry(id),
    credit_entry_id       BIGINT NOT NULL REFERENCES reglement_ledger_entry(id),
    matched_amount_minor  BIGINT NOT NULL CHECK (matched_amount_minor > 0),
    is_automatic          BOOLEAN NOT NULL DEFAULT false,
    match_group           VARCHAR(30),
    matched_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    matched_by            BIGINT REFERENCES party_account(id),
    unmatched_at          TIMESTAMPTZ,
    unmatched_by          BIGINT REFERENCES party_account(id),
    CONSTRAINT chk_matching_distinct CHECK (debit_entry_id <> credit_entry_id)
);

COMMENT ON TABLE reglement_matching IS
'Lettrage N-N optionnel. Ne touche pas le solde. Même livre = règle applicative.
 Restant crédit = |credit.amount_minor| - SUM(matched actifs).';

CREATE INDEX IF NOT EXISTS idx_reglement_matching_debit ON reglement_matching(debit_entry_id) WHERE unmatched_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_reglement_matching_credit ON reglement_matching(credit_entry_id) WHERE unmatched_at IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_reglement_matching_public_id ON reglement_matching(public_id);
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
