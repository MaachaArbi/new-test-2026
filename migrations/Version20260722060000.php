<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire le DEFAULT 'TND' temporaire sur achat/vente_currency_code :
 * le Domain fournit désormais toujours une valeur explicite (NOT NULL schéma).
 */
final class Version20260722060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'booking: DROP DEFAULT on achat/vente_currency_code (Domain supplies values)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking ALTER COLUMN achat_currency_code DROP DEFAULT');
        $this->addSql('ALTER TABLE booking ALTER COLUMN vente_currency_code DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE booking ALTER COLUMN achat_currency_code SET DEFAULT 'TND'");
        $this->addSql("ALTER TABLE booking ALTER COLUMN vente_currency_code SET DEFAULT 'TND'");
    }
}
