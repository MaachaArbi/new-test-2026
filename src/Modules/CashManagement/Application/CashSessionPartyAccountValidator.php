<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application;

use App\Modules\CashManagement\Domain\Exception\CashSessionReferencedAccountNotFoundException;
use Doctrine\DBAL\Connection;

/**
 * Existence party_account avant écriture cash_session (ADR-003 DBAL).
 */
final class CashSessionPartyAccountValidator
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function assertHolderExists(int $accountId): void
    {
        if (!$this->exists($accountId)) {
            throw CashSessionReferencedAccountNotFoundException::forHolder($accountId);
        }
    }

    public function assertOfficeExists(int $accountId): void
    {
        if (!$this->exists($accountId)) {
            throw CashSessionReferencedAccountNotFoundException::forOffice($accountId);
        }
    }

    public function assertOpenedByExists(int $accountId): void
    {
        if (!$this->exists($accountId)) {
            throw CashSessionReferencedAccountNotFoundException::forOpenedBy($accountId);
        }
    }

    public function assertClosedByExists(int $accountId): void
    {
        if (!$this->exists($accountId)) {
            throw CashSessionReferencedAccountNotFoundException::forClosedBy($accountId);
        }
    }

    private function exists(int $accountId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account WHERE id = :id AND deleted_at IS NULL',
            ['id' => $accountId],
        );

        return $raw !== false && $raw !== null;
    }
}
