<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Doctrine\AbstractReferenceSqlMigration;
use Doctrine\DBAL\Schema\Schema;

/** Module Party — inclut déjà party_account_group* dans le package reference/ 2026-07-21 */
final class Version20260721110101 extends AbstractReferenceSqlMigration
{
    public function getDescription(): string
    {
        return 'Import reference/schemas/schema-party-account-v1.sql';
    }

    protected function referenceRelativePath(): string
    {
        return 'schema-party-account-v1.sql';
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
