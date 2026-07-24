<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Renommage FR→EN des identifiants BDD (§39 périmètre A).
 *
 * reglement_* → settlement_* ; pointvente → sales_point ;
 * pointvente_paiement → sales_point_payment.
 * Généré depuis le catalogue PostgreSQL (tables/colonnes/contraintes/index/
 * fonctions/triggers/séquences + index de partitions booking).
 * Corps des fonctions réécrits pour pointer vers les tables renommées.
 * Valeurs de codes préservées (reglement_client/fournisseur/direct — §64).
 */
final class Version20260724100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename reglement_/pointvente identifiers to settlement_/sales_point (§39 A)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
-- Generated from pg_catalog — rename FR→EN identifiers (§39 A)
BEGIN;
ALTER TABLE reglement_balance RENAME TO settlement_balance;
ALTER TABLE reglement_entry_type RENAME TO settlement_entry_type;
ALTER TABLE reglement_instrument RENAME TO settlement_instrument;
ALTER TABLE reglement_ledger_entry RENAME TO settlement_ledger_entry;
ALTER TABLE reglement_matching RENAME TO settlement_matching;
ALTER TABLE reglement_payment_method RENAME TO settlement_payment_method;
ALTER TABLE reglement_transfer RENAME TO settlement_transfer;
ALTER TABLE booking RENAME COLUMN pointvente_id TO sales_point_id;
ALTER TABLE booking RENAME COLUMN pointvente_paiement_id TO sales_point_payment_id;
ALTER TABLE settlement_balance RENAME CONSTRAINT reglement_balance_currency_code_fkey TO settlement_balance_currency_code_fkey;
ALTER TABLE settlement_balance RENAME CONSTRAINT reglement_balance_last_entry_id_fkey TO settlement_balance_last_entry_id_fkey;
ALTER TABLE settlement_balance RENAME CONSTRAINT reglement_balance_party_account_id_fkey TO settlement_balance_party_account_id_fkey;
ALTER TABLE settlement_balance RENAME CONSTRAINT reglement_balance_pkey TO settlement_balance_pkey;
ALTER TABLE settlement_entry_type RENAME CONSTRAINT reglement_entry_type_code_key TO settlement_entry_type_code_key;
ALTER TABLE settlement_entry_type RENAME CONSTRAINT reglement_entry_type_normal_sign_check TO settlement_entry_type_normal_sign_check;
ALTER TABLE settlement_entry_type RENAME CONSTRAINT reglement_entry_type_pkey TO settlement_entry_type_pkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_amount_minor_check TO settlement_instrument_amount_minor_check;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_created_by_fkey TO settlement_instrument_created_by_fkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_currency_code_fkey TO settlement_instrument_currency_code_fkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_office_account_id_fkey TO settlement_instrument_office_account_id_fkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_party_account_id_fkey TO settlement_instrument_party_account_id_fkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_party_role_check TO settlement_instrument_party_role_check;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_payment_method_id_fkey TO settlement_instrument_payment_method_id_fkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_pkey TO settlement_instrument_pkey;
ALTER TABLE settlement_instrument RENAME CONSTRAINT reglement_instrument_status_code_check TO settlement_instrument_status_code_check;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_amount_minor_check TO settlement_ledger_entry_amount_minor_check;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_created_by_fkey TO settlement_ledger_entry_created_by_fkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_currency_code_fkey TO settlement_ledger_entry_currency_code_fkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_entry_type_id_fkey TO settlement_ledger_entry_entry_type_id_fkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_instrument_id_fkey TO settlement_ledger_entry_instrument_id_fkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_party_account_id_fkey TO settlement_ledger_entry_party_account_id_fkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_party_role_check TO settlement_ledger_entry_party_role_check;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_pkey TO settlement_ledger_entry_pkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_reverses_entry_id_fkey TO settlement_ledger_entry_reverses_entry_id_fkey;
ALTER TABLE settlement_ledger_entry RENAME CONSTRAINT reglement_ledger_entry_transfer_id_fkey TO settlement_ledger_entry_transfer_id_fkey;
ALTER TABLE settlement_matching RENAME CONSTRAINT reglement_matching_credit_entry_id_fkey TO settlement_matching_credit_entry_id_fkey;
ALTER TABLE settlement_matching RENAME CONSTRAINT reglement_matching_debit_entry_id_fkey TO settlement_matching_debit_entry_id_fkey;
ALTER TABLE settlement_matching RENAME CONSTRAINT reglement_matching_matched_amount_minor_check TO settlement_matching_matched_amount_minor_check;
ALTER TABLE settlement_matching RENAME CONSTRAINT reglement_matching_matched_by_fkey TO settlement_matching_matched_by_fkey;
ALTER TABLE settlement_matching RENAME CONSTRAINT reglement_matching_pkey TO settlement_matching_pkey;
ALTER TABLE settlement_matching RENAME CONSTRAINT reglement_matching_unmatched_by_fkey TO settlement_matching_unmatched_by_fkey;
ALTER TABLE settlement_payment_method RENAME CONSTRAINT reglement_payment_method_code_key TO settlement_payment_method_code_key;
ALTER TABLE settlement_payment_method RENAME CONSTRAINT reglement_payment_method_pkey TO settlement_payment_method_pkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_amount_minor_check TO settlement_transfer_amount_minor_check;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_created_by_fkey TO settlement_transfer_created_by_fkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_currency_code_fkey TO settlement_transfer_currency_code_fkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_pkey TO settlement_transfer_pkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_reverses_transfer_id_fkey TO settlement_transfer_reverses_transfer_id_fkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_source_account_id_fkey TO settlement_transfer_source_account_id_fkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_source_role_check TO settlement_transfer_source_role_check;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_target_account_id_fkey TO settlement_transfer_target_account_id_fkey;
ALTER TABLE settlement_transfer RENAME CONSTRAINT reglement_transfer_target_role_check TO settlement_transfer_target_role_check;
ALTER INDEX idx_booking_pointvente RENAME TO idx_booking_sales_point;
ALTER INDEX idx_booking_pointvente_paiement RENAME TO idx_booking_sales_point_payment;
ALTER INDEX uq_reglement_entry_type_public_id RENAME TO uq_settlement_entry_type_public_id;
ALTER INDEX idx_reglement_instrument_party RENAME TO idx_settlement_instrument_party;
ALTER INDEX idx_reglement_instrument_status RENAME TO idx_settlement_instrument_status;
ALTER INDEX uq_reglement_instrument_public_id RENAME TO uq_settlement_instrument_public_id;
ALTER INDEX idx_reglement_ledger_book RENAME TO idx_settlement_ledger_book;
ALTER INDEX idx_reglement_ledger_booking RENAME TO idx_settlement_ledger_booking;
ALTER INDEX idx_reglement_ledger_instrument RENAME TO idx_settlement_ledger_instrument;
ALTER INDEX idx_reglement_ledger_transfer RENAME TO idx_settlement_ledger_transfer;
ALTER INDEX uq_reglement_ledger_entry_public_id RENAME TO uq_settlement_ledger_entry_public_id;
ALTER INDEX idx_reglement_matching_credit RENAME TO idx_settlement_matching_credit;
ALTER INDEX idx_reglement_matching_debit RENAME TO idx_settlement_matching_debit;
ALTER INDEX uq_reglement_matching_public_id RENAME TO uq_settlement_matching_public_id;
ALTER INDEX uq_reglement_payment_method_public_id RENAME TO uq_settlement_payment_method_public_id;
ALTER INDEX idx_reglement_transfer_source RENAME TO idx_settlement_transfer_source;
ALTER INDEX idx_reglement_transfer_target RENAME TO idx_settlement_transfer_target;
ALTER INDEX uq_reglement_transfer_public_id RENAME TO uq_settlement_transfer_public_id;
-- Rewrite function bodies to reference renamed tables, then rename functions
CREATE OR REPLACE FUNCTION public.reglement_balance_apply()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
    INSERT INTO settlement_balance AS b
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
$function$;
ALTER FUNCTION reglement_balance_apply() RENAME TO settlement_balance_apply;
CREATE OR REPLACE FUNCTION public.reglement_ledger_block_mutation()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
    RAISE EXCEPTION
        'settlement_ledger_entry est append-only : % interdit (id=%). '
        'Toute correction est une écriture nouvelle (contre-passation).',
        TG_OP, OLD.id;
END;
$function$;
ALTER FUNCTION reglement_ledger_block_mutation() RENAME TO settlement_ledger_block_mutation;
CREATE OR REPLACE FUNCTION public.reglement_post_transfer(p_source_id bigint, p_source_role character varying, p_target_id bigint, p_target_role character varying, p_currency character varying, p_amount bigint, p_date date, p_reason text, p_created_by bigint DEFAULT NULL::bigint)
 RETURNS bigint
 LANGUAGE plpgsql
AS $function$
DECLARE
    v_transfer_id BIGINT;
    v_type_id     BIGINT;
BEGIN
    SELECT id INTO v_type_id
    FROM settlement_entry_type WHERE code = 'transfert_solde';

    INSERT INTO settlement_transfer
        (source_account_id, source_role, target_account_id, target_role,
         currency_code, amount_minor, effective_date, reason, created_by)
    VALUES
        (p_source_id, p_source_role, p_target_id, p_target_role,
         p_currency, p_amount, p_date, p_reason, p_created_by)
    RETURNING id INTO v_transfer_id;

    INSERT INTO settlement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, transfer_id, memo, created_by)
    VALUES
        (p_source_id, p_source_role, p_currency, v_type_id,
         -p_amount, p_date, v_transfer_id,
         'Transfert sortant : ' || coalesce(p_reason, ''), p_created_by);

    INSERT INTO settlement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, transfer_id, memo, created_by)
    VALUES
        (p_target_id, p_target_role, p_currency, v_type_id,
         p_amount, p_date, v_transfer_id,
         'Transfert entrant : ' || coalesce(p_reason, ''), p_created_by);

    RETURN v_transfer_id;
END;
$function$;
ALTER FUNCTION reglement_post_transfer(p_source_id bigint, p_source_role character varying, p_target_id bigint, p_target_role character varying, p_currency character varying, p_amount bigint, p_date date, p_reason text, p_created_by bigint) RENAME TO settlement_post_transfer;
ALTER TRIGGER trg_reglement_balance_apply ON settlement_ledger_entry RENAME TO trg_settlement_balance_apply;
ALTER TRIGGER trg_reglement_ledger_no_mutation ON settlement_ledger_entry RENAME TO trg_settlement_ledger_no_mutation;
ALTER SEQUENCE reglement_entry_type_id_seq RENAME TO settlement_entry_type_id_seq;
ALTER SEQUENCE reglement_instrument_id_seq RENAME TO settlement_instrument_id_seq;
ALTER SEQUENCE reglement_ledger_entry_id_seq RENAME TO settlement_ledger_entry_id_seq;
ALTER SEQUENCE reglement_matching_id_seq RENAME TO settlement_matching_id_seq;
ALTER SEQUENCE reglement_payment_method_id_seq RENAME TO settlement_payment_method_id_seq;
ALTER SEQUENCE reglement_transfer_id_seq RENAME TO settlement_transfer_id_seq;
ALTER INDEX booking_default_pointvente_id_idx RENAME TO booking_default_sales_point_id_idx;
ALTER INDEX booking_default_pointvente_paiement_id_idx RENAME TO booking_default_sales_point_payment_id_idx;
ALTER INDEX booking_y2026m07_pointvente_id_idx RENAME TO booking_y2026m07_sales_point_id_idx;
ALTER INDEX booking_y2026m07_pointvente_paiement_id_idx RENAME TO booking_y2026m07_sales_point_payment_id_idx;
ALTER INDEX booking_y2026m08_pointvente_id_idx RENAME TO booking_y2026m08_sales_point_id_idx;
ALTER INDEX booking_y2026m08_pointvente_paiement_id_idx RENAME TO booking_y2026m08_sales_point_payment_id_idx;
ALTER INDEX booking_y2026m09_pointvente_id_idx RENAME TO booking_y2026m09_sales_point_id_idx;
ALTER INDEX booking_y2026m09_pointvente_paiement_id_idx RENAME TO booking_y2026m09_sales_point_payment_id_idx;

-- Corps des fonctions hors préfixe reglement_/pointvente qui référencent les tables renommées
CREATE OR REPLACE FUNCTION public.cash_receive_instrument(p_session_id bigint, p_instrument_id bigint, p_by bigint)
 RETURNS bigint
 LANGUAGE plpgsql
AS $function$
DECLARE
    v_type_id BIGINT; v_currency VARCHAR(3); v_amount BIGINT; v_movement_id BIGINT;
BEGIN
    SELECT currency_code, amount_minor INTO v_currency, v_amount
    FROM settlement_instrument WHERE id = p_instrument_id;
    IF v_currency IS NULL THEN
        RAISE EXCEPTION 'Instrument % introuvable', p_instrument_id;
    END IF;

    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'encaissement_instrument';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, created_by)
    VALUES (p_session_id, v_type_id, v_currency, v_amount, p_instrument_id, p_by)
    RETURNING id INTO v_movement_id;
    RETURN v_movement_id;
END; $function$;

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
