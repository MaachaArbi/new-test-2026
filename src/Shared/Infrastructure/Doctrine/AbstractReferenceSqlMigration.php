<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Charge un fichier SQL (ou .diff unifié additif) depuis reference/schemas/
 * sans jamais générer de schéma via Doctrine.
 */
abstract class AbstractReferenceSqlMigration extends AbstractMigration
{
    abstract protected function referenceRelativePath(): string;

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $path = $this->projectDir().'/reference/schemas/'.$this->referenceRelativePath();
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Fichier de référence introuvable : %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Impossible de lire : %s', $path));
        }

        $sql = str_ends_with($this->referenceRelativePath(), '.diff')
            ? $this->unifiedDiffToSql($raw)
            : $raw;

        $this->execSqlFile($this->connection, $sql);
    }

    public function down(\Doctrine\DBAL\Schema\Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Les migrations SQL de référence ne sont pas réversibles automatiquement.'
        );
    }

    protected function projectDir(): string
    {
        return dirname(__DIR__, 4);
    }

    protected function execSqlFile(Connection $connection, string $sql): void
    {
        $native = $connection->getNativeConnection();
        if (!$native instanceof \PDO) {
            throw new RuntimeException('Connexion PDO native attendue pour exécuter le SQL multi-statements.');
        }

        $native->exec($sql);
    }

    protected function unifiedDiffToSql(string $diff): string
    {
        $out = [];
        $lines = preg_split("/\r\n|\n|\r/", $diff);
        if ($lines === false) {
            return "\n";
        }

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '---') || str_starts_with($line, '+++') || str_starts_with($line, '@@')) {
                continue;
            }
            if (str_starts_with($line, '+')) {
                $out[] = substr($line, 1);
            }
        }

        return implode("\n", $out)."\n";
    }
}
