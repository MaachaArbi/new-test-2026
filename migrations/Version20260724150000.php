<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Référentiels pour colonnes de type en chaîne libre + board_type_snapshot.
 *
 * Runtime : tables présentes = party_account_document, booking_traveler,
 * party_account_address, booking_accommodation_detail.
 * Absentes : log_activity, pricing_rule_log → log_event_type /
 * pricing_log_event_type uniquement dans reference/.
 *
 * SQL encadré BEGIN/COMMIT (décision 2026-07-24 migrations atomiques).
 */
final class Version20260724150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Referentials for free-text type columns + board_type_snapshot rename';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
BEGIN;

-- ref_document_type (partagé Party + Booking)
CREATE TABLE IF NOT EXISTS ref_document_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS ref_document_type_translation (
    document_type_code  VARCHAR(30) NOT NULL REFERENCES ref_document_type(code),
    language_code       VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label               VARCHAR(100) NOT NULL,
    description         TEXT,
    PRIMARY KEY (document_type_code, language_code)
);

INSERT INTO ref_document_type (code, sort_order) VALUES
    ('passport', 0), ('cin', 1), ('driving_license', 2),
    ('contract', 3), ('logo', 4), ('other', 5)
ON CONFLICT (code) DO NOTHING;

INSERT INTO ref_document_type_translation (document_type_code, language_code, label) VALUES
    ('passport', 'en', 'Passport'),
    ('passport', 'fr', 'Passeport'),
    ('passport', 'ar', 'جواز سفر'),
    ('cin', 'en', 'National ID'),
    ('cin', 'fr', 'CIN'),
    ('cin', 'ar', 'بطاقة تعريف'),
    ('driving_license', 'en', 'Driving licence'),
    ('driving_license', 'fr', 'Permis de conduire'),
    ('driving_license', 'ar', 'رخصة سياقة'),
    ('contract', 'en', 'Contract'),
    ('contract', 'fr', 'Contrat'),
    ('contract', 'ar', 'عقد'),
    ('logo', 'en', 'Logo'),
    ('logo', 'fr', 'Logo'),
    ('logo', 'ar', 'شعار'),
    ('other', 'en', 'Other'),
    ('other', 'fr', 'Autre'),
    ('other', 'ar', 'أخرى')
ON CONFLICT DO NOTHING;

ALTER TABLE party_account_document
    ALTER COLUMN document_type TYPE VARCHAR(30);
ALTER TABLE party_account_document
    DROP CONSTRAINT IF EXISTS party_account_document_document_type_fkey;
ALTER TABLE party_account_document
    ADD CONSTRAINT party_account_document_document_type_fkey
    FOREIGN KEY (document_type) REFERENCES ref_document_type(code);

ALTER TABLE booking_traveler
    DROP CONSTRAINT IF EXISTS booking_traveler_document_type_fkey;
ALTER TABLE booking_traveler
    ADD CONSTRAINT booking_traveler_document_type_fkey
    FOREIGN KEY (document_type) REFERENCES ref_document_type(code);

-- party_address_type
CREATE TABLE IF NOT EXISTS party_address_type (
    code        VARCHAR(30) PRIMARY KEY,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS party_address_type_translation (
    address_type_code  VARCHAR(30) NOT NULL REFERENCES party_address_type(code),
    language_code      VARCHAR(5) NOT NULL REFERENCES ref_language(code),
    label              VARCHAR(100) NOT NULL,
    description        TEXT,
    PRIMARY KEY (address_type_code, language_code)
);

INSERT INTO party_address_type (code, sort_order) VALUES
    ('legal', 0), ('billing', 1), ('delivery', 2),
    ('domiciliation', 3), ('other', 4)
ON CONFLICT (code) DO NOTHING;

INSERT INTO party_address_type_translation (address_type_code, language_code, label) VALUES
    ('legal', 'en', 'Legal'),
    ('legal', 'fr', 'Légale'),
    ('legal', 'ar', 'قانونية'),
    ('billing', 'en', 'Billing'),
    ('billing', 'fr', 'Facturation'),
    ('billing', 'ar', 'فوترة'),
    ('delivery', 'en', 'Delivery'),
    ('delivery', 'fr', 'Livraison'),
    ('delivery', 'ar', 'تسليم'),
    ('domiciliation', 'en', 'Domiciliation'),
    ('domiciliation', 'fr', 'Domiciliation'),
    ('domiciliation', 'ar', 'موطن'),
    ('other', 'en', 'Other'),
    ('other', 'fr', 'Autre'),
    ('other', 'ar', 'أخرى')
ON CONFLICT DO NOTHING;

ALTER TABLE party_account_address
    DROP CONSTRAINT IF EXISTS party_account_address_address_type_fkey;
ALTER TABLE party_account_address
    ADD CONSTRAINT party_account_address_address_type_fkey
    FOREIGN KEY (address_type) REFERENCES party_address_type(code);

-- board_type → board_type_snapshot (texte libre volontaire)
ALTER TABLE booking_accommodation_detail
    RENAME COLUMN board_type TO board_type_snapshot;

COMMENT ON COLUMN booking_accommodation_detail.board_type_snapshot IS
'libellé commercial de l''arrangement tel qu''annoncé par le fournisseur, figé à la vente -- TEXTE LIBRE VOLONTAIRE, PAS un code de référentiel ; les exemples ci-dessus ne sont pas une liste fermée';

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
