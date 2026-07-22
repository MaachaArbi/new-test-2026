<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice Règlements V1 — référentiels + instrument uniquement.
 *
 * Hors périmètre volontaire : ledger_entry (append-only/trigger), balance,
 * matching, transfer, reglement_post_transfer() — vague ledger séparée.
 */
final class Version20260722170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import reglement_payment_method + reglement_entry_type + reglement_instrument (seeds inclus)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS reglement_payment_method (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id     UUID NOT NULL DEFAULT gen_random_uuid(),
    code          VARCHAR(4)  NOT NULL UNIQUE,
    label         VARCHAR(60) NOT NULL,
    is_cash_like  BOOLEAN NOT NULL DEFAULT false,
    is_active     BOOLEAN NOT NULL DEFAULT true,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE reglement_payment_method IS
'Modes de règlement (table, jamais ENUM). is_cash_like = crochet Cash Management.
 Valeurs initiales : AD, CB, C, E, V, VE, LC, PC, RC, PE, RI.
 NB : AD conservé pour migration historique — ne plus créer en saisie.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_reglement_payment_method_public_id ON reglement_payment_method(public_id);

CREATE TABLE IF NOT EXISTS reglement_entry_type (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id     UUID NOT NULL DEFAULT gen_random_uuid(),
    code          VARCHAR(30) NOT NULL UNIQUE,
    label         VARCHAR(80) NOT NULL,
    normal_sign   SMALLINT NOT NULL CHECK (normal_sign IN (-1, 1)),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE reglement_entry_type IS
'Nature des écritures du grand livre. Extensible sans migration.
 Seed : obligation_vente/achat, reglement_client/fournisseur, reversal,
 deposit, remboursement_client, transfert_solde.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_reglement_entry_type_public_id ON reglement_entry_type(public_id);

CREATE TABLE IF NOT EXISTS reglement_instrument (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    party_role         VARCHAR(20) NOT NULL
                         CHECK (party_role IN ('client','fournisseur')),
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    payment_method_id  BIGINT NOT NULL REFERENCES reglement_payment_method(id),
    amount_minor       BIGINT NOT NULL CHECK (amount_minor > 0),
    instrument_ref     VARCHAR(100),
    bank_name          VARCHAR(150),
    due_date           DATE,
    issued_on          DATE,
    metadata           JSONB NOT NULL DEFAULT '{}'::jsonb,
    status_code        VARCHAR(20) NOT NULL DEFAULT 'active'
                         CHECK (status_code IN ('active','returned','cancelled')),
    status_changed_at  TIMESTAMPTZ,
    status_reason      TEXT,
    office_account_id  BIGINT REFERENCES party_account(id),
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE reglement_instrument IS
'Pièce de règlement. amount_minor immuable ; restant = dérivé du lettrage (hors vague).
 Un retour/annulation ne mute pas le grand livre ici — écriture inverse = vague ledger.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_reglement_instrument_public_id ON reglement_instrument(public_id);
CREATE INDEX IF NOT EXISTS idx_reglement_instrument_party ON reglement_instrument(party_account_id, currency_code);
CREATE INDEX IF NOT EXISTS idx_reglement_instrument_status ON reglement_instrument(status_code)
    WHERE status_code <> 'active';

INSERT INTO reglement_payment_method (code, label, is_cash_like) VALUES
    ('AD', 'Autorisation de débit',               false),
    ('CB', 'Carte Bancaire',                       false),
    ('C',  'Chèque',                               true),
    ('E',  'Espèce',                               true),
    ('V',  'Virement bancaire',                    false),
    ('VE', 'Versement espèce',                     false),
    ('LC', 'Lettre de change',                     true),
    ('PC', 'Prise en charge / Bon de Commande',    true),
    ('RC', 'Retenue à la source',                  true),
    ('PE', 'Paiement électronique',                false),
    ('RI', 'Ristourne',                            false)
ON CONFLICT (code) DO NOTHING;

INSERT INTO reglement_entry_type (code, label, normal_sign) VALUES
    ('obligation_vente',      'Obligation client (réservation validée)',         1),
    ('obligation_achat',      'Obligation fournisseur (rattachement réservation)',1),
    ('reglement_client',      'Règlement reçu du client',                       -1),
    ('reglement_fournisseur', 'Règlement versé au fournisseur',                 -1),
    ('reversal',              'Contre-passation',                                1),
    ('deposit',               'Dépôt / avance (sans réservation en face)',      -1),
    ('remboursement_client',  'Remboursement sortant vers client',               1),
    ('transfert_solde',       'Jambe de transfert inter-livres',                 1)
ON CONFLICT (code) DO NOTHING;
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
