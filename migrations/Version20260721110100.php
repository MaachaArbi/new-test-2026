<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Doctrine\AbstractReferenceSqlMigration;
use Doctrine\DBAL\Schema\Schema;

/** ref_language + ref_currency */
final class Version20260721110100 extends AbstractReferenceSqlMigration
{
    public function getDescription(): string
    {
        return 'Import reference/schemas/schema-ref-common.sql';
    }

    protected function referenceRelativePath(): string
    {
        return 'schema-ref-common.sql';
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
