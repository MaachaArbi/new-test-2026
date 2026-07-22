<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Cash Management — référentiel routing uniquement.
 *
 * Aligné sur reference/schemas/schema-cash-management-v1.sql (§1 ROUTING).
 * Hors périmètre : cash_session, cash_movement, fonctions PL/pgSQL.
 */
final class Version20260722200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import cash_routing_type + cash_payment_method_routing (seed inclus)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE cash_routing_type (
    code        VARCHAR(20) PRIMARY KEY,
    label       VARCHAR(80) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_routing_type IS
'Destination physique d''un mode de règlement une fois la pièce créée dans Règlements.
 caisse = transite par une session utilisateur ; banque_directe = atterrit
 directement en banque sans jamais passer par une caisse (ex: virement reçu,
 versement espèce au guichet bancaire) ; transmission_externe = doit être
 physiquement transmis à un tiers émetteur avant remboursement (ex: bon de
 commande amicale) ; aucun = scriptural pur, Cash Management ne le voit jamais.';

INSERT INTO cash_routing_type (code, label) VALUES
    ('caisse',               'Passe par une session de caisse'),
    ('banque_directe',       'Atterrit directement en banque, sans caisse'),
    ('transmission_externe', 'Transmis physiquement à un tiers émetteur'),
    ('aucun',                'Scriptural pur, hors périmètre Cash Management');

CREATE TABLE cash_payment_method_routing (
    payment_method_id        BIGINT PRIMARY KEY REFERENCES reglement_payment_method(id),
    routing_type_code        VARCHAR(20) NOT NULL REFERENCES cash_routing_type(code),

    instrument_tracking_mode VARCHAR(20) NOT NULL DEFAULT 'not_applicable'
                                CHECK (instrument_tracking_mode IN ('individual','aggregate','not_applicable')),

    strict_source_isolation  BOOLEAN NOT NULL DEFAULT false,

    requires_custody_check   BOOLEAN NOT NULL DEFAULT true,
    is_active                BOOLEAN NOT NULL DEFAULT true,

    created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_routing_tracking_consistency CHECK (
        (routing_type_code = 'aucun' AND instrument_tracking_mode = 'not_applicable')
        OR (routing_type_code <> 'aucun' AND instrument_tracking_mode <> 'not_applicable')
    )
);

COMMENT ON TABLE cash_payment_method_routing IS
'Extension 1-1 de reglement_payment_method (même pattern que party_account_office
 sur party_account). Créer un nouveau mode de règlement = ajouter une ligne
 reglement_payment_method + une ligne ici. Le moteur ne connaît plus aucun
 code de mode de règlement en dur : il lit routing_type_code et
 instrument_tracking_mode. is_cash_like (Règlements) répond à "transite
 physiquement" ; routing_type_code répond à "vers où" — les deux peuvent
 diverger (ex: virement V est is_cash_like=false côté Règlements mais
 routing_type_code=banque_directe ici, car il doit être rapproché sur relevé
 même sans jamais toucher une caisse).';

CREATE TRIGGER trg_cash_payment_method_routing_updated_at BEFORE UPDATE ON cash_payment_method_routing
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

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
