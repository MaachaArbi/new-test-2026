<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Doctrine\AbstractReferenceSqlMigration;
use Doctrine\DBAL\Schema\Schema;

/** Module Core — core_credential */
final class Version20260721110103 extends AbstractReferenceSqlMigration
{
    public function getDescription(): string
    {
        return 'Import reference/schemas/schema-core-identity-v1.sql';
    }

    protected function referenceRelativePath(): string
    {
        return 'schema-core-identity-v1.sql';
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
