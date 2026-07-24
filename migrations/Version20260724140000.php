<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * §64 — Renommage FR→EN de 41 codes de référentiels (périmètre B).
 *
 * Groupe 1 (PK=id) : UPDATE code. Groupe 2 (PK=code) : INSERT/UPDATE enfants/DELETE.
 * Groupe 3 : CHECK-only. Corps fonctions SQL réécrits. CHECKs recréés.
 * cash_bank_transaction_type absent en runtime — reference/ seul.
 * Élargissement VARCHAR routing : external_transmission (21) > 20.
 */
final class Version20260724140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename 41 referential codes FR→EN (§64)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
-- §64 : codes référentiels FR→EN (runtime)
BEGIN;

-- Widen routing codes: external_transmission = 21 > VARCHAR(20)
ALTER TABLE cash_routing_type ALTER COLUMN code TYPE VARCHAR(40);
ALTER TABLE cash_payment_method_routing ALTER COLUMN routing_type_code TYPE VARCHAR(40);

-- PHASE 0 — DROP CHECKs
ALTER TABLE party_account_office_relation DROP CONSTRAINT IF EXISTS party_account_office_relation_relation_type_check;
ALTER TABLE booking_settlement DROP CONSTRAINT IF EXISTS booking_settlement_beneficiary_role_check;
ALTER TABLE settlement_instrument DROP CONSTRAINT IF EXISTS settlement_instrument_party_role_check;
ALTER TABLE settlement_ledger_entry DROP CONSTRAINT IF EXISTS settlement_ledger_entry_party_role_check;
ALTER TABLE settlement_transfer DROP CONSTRAINT IF EXISTS settlement_transfer_source_role_check;
ALTER TABLE settlement_transfer DROP CONSTRAINT IF EXISTS settlement_transfer_target_role_check;
ALTER TABLE cash_payment_method_routing DROP CONSTRAINT IF EXISTS chk_routing_tracking_consistency;

-- PHASE 1 — GROUPE 1
UPDATE settlement_entry_type SET code = 'customer_obligation' WHERE code = 'obligation_vente';
UPDATE settlement_entry_type SET code = 'supplier_obligation' WHERE code = 'obligation_achat';
UPDATE settlement_entry_type SET code = 'customer_payment' WHERE code = 'reglement_client';
UPDATE settlement_entry_type SET code = 'supplier_payment' WHERE code = 'reglement_fournisseur';
UPDATE settlement_entry_type SET code = 'customer_refund' WHERE code = 'remboursement_client';
UPDATE settlement_entry_type SET code = 'balance_transfer' WHERE code = 'transfert_solde';
UPDATE cash_movement_type SET code = 'instrument_receipt' WHERE code = 'encaissement_instrument';
UPDATE cash_movement_type SET code = 'supplier_disbursement' WHERE code = 'decaissement_fournisseur';
UPDATE cash_movement_type SET code = 'free_credit' WHERE code = 'mouvement_libre_credit';
UPDATE cash_movement_type SET code = 'free_debit' WHERE code = 'mouvement_libre_debit';
UPDATE cash_movement_type SET code = 'transfer_out' WHERE code = 'transfert_sortant';
UPDATE cash_movement_type SET code = 'transfer_in' WHERE code = 'transfert_entrant';
UPDATE cash_movement_type SET code = 'conversion_out' WHERE code = 'conversion_sortante';
UPDATE cash_movement_type SET code = 'conversion_in' WHERE code = 'conversion_entrante';
UPDATE cash_movement_type SET code = 'bank_deposit_out' WHERE code = 'sortie_depot_banque';
UPDATE cash_movement_type SET code = 'external_transmission_out' WHERE code = 'sortie_transmission_externe';
UPDATE cash_movement_type SET code = 'session_validation_out' WHERE code = 'sortie_validation_session';
UPDATE cash_movement_type SET code = 'session_validation_in' WHERE code = 'entree_validation_session';
UPDATE cash_movement_type SET code = 'returned_instrument_out' WHERE code = 'sortie_instrument_retourne';
UPDATE cash_movement_type SET code = 'closing_variance' WHERE code = 'ecart_cloture';
UPDATE cash_movement_type SET code = 'generic_correction' WHERE code = 'correction_generique';

-- PHASE 2 — GROUPE 2
ALTER TABLE settlement_ledger_entry DISABLE TRIGGER trg_settlement_ledger_no_mutation;
-- party_role: client -> customer
INSERT INTO party_role (code, sort_order, created_at) SELECT 'customer', sort_order, created_at FROM party_role WHERE code = 'client';
UPDATE party_account_role SET role_code = 'customer' WHERE role_code = 'client';
UPDATE party_role_translation SET role_code = 'customer' WHERE role_code = 'client';
UPDATE party_account_office_relation SET relation_type = 'customer' WHERE relation_type = 'client';
DELETE FROM party_role WHERE code = 'client';
UPDATE settlement_instrument SET party_role = 'customer' WHERE party_role = 'client';
UPDATE settlement_ledger_entry SET party_role = 'customer' WHERE party_role = 'client';
UPDATE settlement_transfer SET source_role = 'customer' WHERE source_role = 'client';
UPDATE settlement_transfer SET target_role = 'customer' WHERE target_role = 'client';
UPDATE settlement_balance SET party_role = 'customer' WHERE party_role = 'client';
-- party_role: fournisseur -> supplier
INSERT INTO party_role (code, sort_order, created_at) SELECT 'supplier', sort_order, created_at FROM party_role WHERE code = 'fournisseur';
UPDATE party_account_role SET role_code = 'supplier' WHERE role_code = 'fournisseur';
UPDATE party_role_translation SET role_code = 'supplier' WHERE role_code = 'fournisseur';
UPDATE party_account_office_relation SET relation_type = 'supplier' WHERE relation_type = 'fournisseur';
DELETE FROM party_role WHERE code = 'fournisseur';
UPDATE settlement_instrument SET party_role = 'supplier' WHERE party_role = 'fournisseur';
UPDATE settlement_ledger_entry SET party_role = 'supplier' WHERE party_role = 'fournisseur';
UPDATE settlement_transfer SET source_role = 'supplier' WHERE source_role = 'fournisseur';
UPDATE settlement_transfer SET target_role = 'supplier' WHERE target_role = 'fournisseur';
UPDATE settlement_balance SET party_role = 'supplier' WHERE party_role = 'fournisseur';
ALTER TABLE settlement_ledger_entry ENABLE TRIGGER trg_settlement_ledger_no_mutation;
-- party_function: gerant -> manager
INSERT INTO party_function (code, sort_order, created_at) SELECT 'manager', sort_order, created_at FROM party_function WHERE code = 'gerant';
UPDATE party_account_function SET function_code = 'manager' WHERE function_code = 'gerant';
UPDATE party_function_translation SET function_code = 'manager' WHERE function_code = 'gerant';
DELETE FROM party_function WHERE code = 'gerant';
-- party_function: financier -> finance
INSERT INTO party_function (code, sort_order, created_at) SELECT 'finance', sort_order, created_at FROM party_function WHERE code = 'financier';
UPDATE party_account_function SET function_code = 'finance' WHERE function_code = 'financier';
UPDATE party_function_translation SET function_code = 'finance' WHERE function_code = 'financier';
DELETE FROM party_function WHERE code = 'financier';
-- party_function: agent_reservation -> booking_agent
INSERT INTO party_function (code, sort_order, created_at) SELECT 'booking_agent', sort_order, created_at FROM party_function WHERE code = 'agent_reservation';
UPDATE party_account_function SET function_code = 'booking_agent' WHERE function_code = 'agent_reservation';
UPDATE party_function_translation SET function_code = 'booking_agent' WHERE function_code = 'agent_reservation';
DELETE FROM party_function WHERE code = 'agent_reservation';
-- cash_routing_type: caisse -> cash_session
INSERT INTO cash_routing_type (code, label, created_at) SELECT 'cash_session', label, created_at FROM cash_routing_type WHERE code = 'caisse';
UPDATE cash_payment_method_routing SET routing_type_code = 'cash_session' WHERE routing_type_code = 'caisse';
DELETE FROM cash_routing_type WHERE code = 'caisse';
-- cash_routing_type: banque_directe -> direct_bank
INSERT INTO cash_routing_type (code, label, created_at) SELECT 'direct_bank', label, created_at FROM cash_routing_type WHERE code = 'banque_directe';
UPDATE cash_payment_method_routing SET routing_type_code = 'direct_bank' WHERE routing_type_code = 'banque_directe';
DELETE FROM cash_routing_type WHERE code = 'banque_directe';
-- cash_routing_type: transmission_externe -> external_transmission
INSERT INTO cash_routing_type (code, label, created_at) SELECT 'external_transmission', label, created_at FROM cash_routing_type WHERE code = 'transmission_externe';
UPDATE cash_payment_method_routing SET routing_type_code = 'external_transmission' WHERE routing_type_code = 'transmission_externe';
DELETE FROM cash_routing_type WHERE code = 'transmission_externe';
-- cash_routing_type: aucun -> none
INSERT INTO cash_routing_type (code, label, created_at) SELECT 'none', label, created_at FROM cash_routing_type WHERE code = 'aucun';
UPDATE cash_payment_method_routing SET routing_type_code = 'none' WHERE routing_type_code = 'aucun';
DELETE FROM cash_routing_type WHERE code = 'aucun';
-- booking_charge_type: margin_agence_principale -> margin_main_agency
INSERT INTO booking_charge_type (code, sort_order, created_at) SELECT 'margin_main_agency', sort_order, created_at FROM booking_charge_type WHERE code = 'margin_agence_principale';
UPDATE booking_charge SET charge_type_code = 'margin_main_agency' WHERE charge_type_code = 'margin_agence_principale';
DELETE FROM booking_charge_type WHERE code = 'margin_agence_principale';
-- booking_charge_type: margin_distributeur -> margin_distributor
INSERT INTO booking_charge_type (code, sort_order, created_at) SELECT 'margin_distributor', sort_order, created_at FROM booking_charge_type WHERE code = 'margin_distributeur';
UPDATE booking_charge SET charge_type_code = 'margin_distributor' WHERE charge_type_code = 'margin_distributeur';
DELETE FROM booking_charge_type WHERE code = 'margin_distributeur';

-- GROUPE 3
UPDATE booking_settlement SET beneficiary_role = 'supplier' WHERE beneficiary_role = 'fournisseur';
UPDATE booking_settlement SET beneficiary_role = 'main_agency' WHERE beneficiary_role = 'agence_principale';
UPDATE booking_settlement SET beneficiary_role = 'distributor' WHERE beneficiary_role = 'distributeur';

-- PHASE 3
CREATE OR REPLACE FUNCTION public.settlement_post_transfer(p_source_id bigint, p_source_role character varying, p_target_id bigint, p_target_role character varying, p_currency character varying, p_amount bigint, p_date date, p_reason text, p_created_by bigint DEFAULT NULL::bigint)
 RETURNS bigint
 LANGUAGE plpgsql
AS $function$
DECLARE
    v_transfer_id BIGINT;
    v_type_id     BIGINT;
BEGIN
    SELECT id INTO v_type_id
    FROM settlement_entry_type WHERE code = 'balance_transfer';

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

    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'instrument_receipt';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, created_by)
    VALUES (p_session_id, v_type_id, v_currency, v_amount, p_instrument_id, p_by)
    RETURNING id INTO v_movement_id;
    RETURN v_movement_id;
END; $function$;

-- PHASE 4
ALTER TABLE party_account_office_relation
  ADD CONSTRAINT party_account_office_relation_relation_type_check
  CHECK (relation_type IN ('customer', 'supplier'));
ALTER TABLE booking_settlement
  ADD CONSTRAINT booking_settlement_beneficiary_role_check
  CHECK (beneficiary_role IN ('supplier', 'main_agency', 'distributor'));
ALTER TABLE settlement_instrument
  ADD CONSTRAINT settlement_instrument_party_role_check
  CHECK (party_role IN ('customer', 'supplier'));
ALTER TABLE settlement_ledger_entry
  ADD CONSTRAINT settlement_ledger_entry_party_role_check
  CHECK (party_role IN ('customer', 'supplier'));
ALTER TABLE settlement_transfer
  ADD CONSTRAINT settlement_transfer_source_role_check
  CHECK (source_role IN ('customer', 'supplier'));
ALTER TABLE settlement_transfer
  ADD CONSTRAINT settlement_transfer_target_role_check
  CHECK (target_role IN ('customer', 'supplier'));
ALTER TABLE cash_payment_method_routing
  ADD CONSTRAINT chk_routing_tracking_consistency
  CHECK (
    (routing_type_code = 'none' AND instrument_tracking_mode = 'not_applicable')
    OR (routing_type_code <> 'none' AND instrument_tracking_mode <> 'not_applicable')
  );
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
