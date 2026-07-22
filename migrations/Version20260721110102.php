<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Shared\Infrastructure\Doctrine\AbstractReferenceSqlMigration;
use Doctrine\DBAL\Schema\Schema;
use RuntimeException;

/**
 * Versionne party-account-group-extension.diff sans ré-appliquer le SQL
 * (déjà fusionné dans schema-party-account-v1.sql).
 *
 * @see docs/decisions/2026-07-21-party-group-extension-already-in-schema.md
 */
final class Version20260721110102 extends AbstractReferenceSqlMigration
{
    public function getDescription(): string
    {
        return 'Versionne party-account-group-extension.diff (no-op : déjà dans schema-party-account-v1.sql)';
    }

    protected function referenceRelativePath(): string
    {
        return 'party-account-group-extension.diff';
    }

    public function up(Schema $schema): void
    {
        $path = $this->projectDir().'/reference/schemas/'.$this->referenceRelativePath();
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Fichier de référence introuvable : %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Impossible de lire : %s', $path));
        }

        $sha = hash('sha256', $raw);
        $this->write(sprintf(
            'party-account-group-extension.diff lu (sha256=%s, %d octets) — non ré-appliqué (déjà inclus dans schema-party-account-v1.sql).',
            $sha,
            strlen($raw)
        ));

        $exists = (bool) $this->connection->fetchOne(
            "SELECT to_regclass('public.party_account_group') IS NOT NULL"
        );
        if (!$exists) {
            throw new RuntimeException(
                'party_account_group absente : schema-party-account-v1.sql aurait dû la créer. '.
                'Ne pas inventer de correctif schéma — remonter à la conception BDD.'
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
