<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Règlements — grand livre append-only + balance + transfer + post_transfer().
 *
 * Hors périmètre : reglement_matching, credit instrument, HTTP.
 */
final class Version20260722180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import reglement_transfer + reglement_ledger_entry (append-only) + reglement_balance + reglement_post_transfer()';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS reglement_transfer (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id            UUID NOT NULL DEFAULT gen_random_uuid(),
    source_account_id    BIGINT NOT NULL REFERENCES party_account(id),
    source_role          VARCHAR(20) NOT NULL CHECK (source_role IN ('client','fournisseur')),
    target_account_id    BIGINT NOT NULL REFERENCES party_account(id),
    target_role          VARCHAR(20) NOT NULL CHECK (target_role IN ('client','fournisseur')),
    currency_code        VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    amount_minor         BIGINT NOT NULL CHECK (amount_minor > 0),
    effective_date       DATE NOT NULL,
    reason               TEXT,
    reverses_transfer_id BIGINT REFERENCES reglement_transfer(id),
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by           BIGINT REFERENCES party_account(id),
    CONSTRAINT chk_transfer_distinct CHECK (
        NOT (source_account_id = target_account_id AND source_role = target_role)
    )
);

COMMENT ON TABLE reglement_transfer IS
'Transfert de solde entre deux livres (même devise). Toujours via reglement_post_transfer().';

CREATE UNIQUE INDEX IF NOT EXISTS uq_reglement_transfer_public_id ON reglement_transfer(public_id);
CREATE INDEX IF NOT EXISTS idx_reglement_transfer_source ON reglement_transfer(source_account_id, currency_code);
CREATE INDEX IF NOT EXISTS idx_reglement_transfer_target ON reglement_transfer(target_account_id, currency_code);

CREATE TABLE IF NOT EXISTS reglement_ledger_entry (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    party_role         VARCHAR(20) NOT NULL
                         CHECK (party_role IN ('client','fournisseur')),
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    entry_type_id      BIGINT NOT NULL REFERENCES reglement_entry_type(id),
    amount_minor       BIGINT NOT NULL CHECK (amount_minor <> 0),
    effective_date     DATE NOT NULL,
    booking_id         BIGINT,
    instrument_id      BIGINT REFERENCES reglement_instrument(id),
    invoice_id         BIGINT,
    credit_note_id     BIGINT,
    transfer_id        BIGINT REFERENCES reglement_transfer(id),
    reverses_entry_id  BIGINT REFERENCES reglement_ledger_entry(id),
    memo               TEXT,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id),
    CONSTRAINT chk_entry_has_origin CHECK (
        booking_id        IS NOT NULL OR
        instrument_id     IS NOT NULL OR
        invoice_id        IS NOT NULL OR
        credit_note_id    IS NOT NULL OR
        reverses_entry_id IS NOT NULL OR
        transfer_id       IS NOT NULL
    )
);

COMMENT ON TABLE reglement_ledger_entry IS
'Grand livre append-only. Correction = nouvelle écriture. INSERT Domain-contrôlé OK tant que post_obligation n''existe pas ; transfert = reglement_post_transfer().';

CREATE INDEX IF NOT EXISTS idx_reglement_ledger_book ON reglement_ledger_entry
    (party_account_id, party_role, currency_code, effective_date, id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_reglement_ledger_entry_public_id ON reglement_ledger_entry(public_id);
CREATE INDEX IF NOT EXISTS idx_reglement_ledger_booking ON reglement_ledger_entry(booking_id) WHERE booking_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_reglement_ledger_instrument ON reglement_ledger_entry(instrument_id) WHERE instrument_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_reglement_ledger_transfer ON reglement_ledger_entry(transfer_id) WHERE transfer_id IS NOT NULL;

CREATE OR REPLACE FUNCTION reglement_ledger_block_mutation()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION
        'reglement_ledger_entry est append-only : % interdit (id=%). '
        'Toute correction est une écriture nouvelle (contre-passation).',
        TG_OP, OLD.id;
END;
$$;

DROP TRIGGER IF EXISTS trg_reglement_ledger_no_mutation ON reglement_ledger_entry;
CREATE TRIGGER trg_reglement_ledger_no_mutation
    BEFORE UPDATE OR DELETE ON reglement_ledger_entry
    FOR EACH ROW EXECUTE FUNCTION reglement_ledger_block_mutation();

CREATE TABLE IF NOT EXISTS reglement_balance (
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    party_role         VARCHAR(20) NOT NULL,
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    balance_minor      BIGINT NOT NULL DEFAULT 0,
    last_entry_id      BIGINT REFERENCES reglement_ledger_entry(id),
    entry_count        BIGINT NOT NULL DEFAULT 0,
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (party_account_id, party_role, currency_code)
);

COMMENT ON TABLE reglement_balance IS
'Snapshot de solde maintenu par trigger AFTER INSERT sur le grand livre. Jamais écrit côté application.';

CREATE OR REPLACE FUNCTION reglement_balance_apply()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO reglement_balance AS b
        (party_account_id, party_role, currency_code, balance_minor, last_entry_id, entry_count)
    VALUES
        (NEW.party_account_id, NEW.party_role, NEW.currency_code,
         NEW.amount_minor, NEW.id, 1)
    ON CONFLICT (party_account_id, party_role, currency_code) DO UPDATE
        SET balance_minor = b.balance_minor + NEW.amount_minor,
            last_entry_id  = NEW.id,
            entry_count    = b.entry_count + 1,
            updated_at     = now();
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_reglement_balance_apply ON reglement_ledger_entry;
CREATE TRIGGER trg_reglement_balance_apply
    AFTER INSERT ON reglement_ledger_entry
    FOR EACH ROW EXECUTE FUNCTION reglement_balance_apply();

CREATE OR REPLACE FUNCTION reglement_post_transfer(
    p_source_id    BIGINT,
    p_source_role  VARCHAR,
    p_target_id    BIGINT,
    p_target_role  VARCHAR,
    p_currency     VARCHAR(3),
    p_amount       BIGINT,
    p_date         DATE,
    p_reason       TEXT,
    p_created_by   BIGINT DEFAULT NULL
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_transfer_id BIGINT;
    v_type_id     BIGINT;
BEGIN
    SELECT id INTO v_type_id
    FROM reglement_entry_type WHERE code = 'transfert_solde';

    INSERT INTO reglement_transfer
        (source_account_id, source_role, target_account_id, target_role,
         currency_code, amount_minor, effective_date, reason, created_by)
    VALUES
        (p_source_id, p_source_role, p_target_id, p_target_role,
         p_currency, p_amount, p_date, p_reason, p_created_by)
    RETURNING id INTO v_transfer_id;

    INSERT INTO reglement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, transfer_id, memo, created_by)
    VALUES
        (p_source_id, p_source_role, p_currency, v_type_id,
         -p_amount, p_date, v_transfer_id,
         'Transfert sortant : ' || coalesce(p_reason, ''), p_created_by);

    INSERT INTO reglement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, transfer_id, memo, created_by)
    VALUES
        (p_target_id, p_target_role, p_currency, v_type_id,
         p_amount, p_date, v_transfer_id,
         'Transfert entrant : ' || coalesce(p_reason, ''), p_created_by);

    RETURN v_transfer_id;
END;
$$;

COMMENT ON FUNCTION reglement_post_transfer IS
'Crée un transfert + 2 jambes atomiquement. Retourne id reglement_transfer.';
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
