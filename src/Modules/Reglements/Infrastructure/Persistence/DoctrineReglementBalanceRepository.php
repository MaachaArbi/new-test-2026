<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Persistence;

use App\Modules\Reglements\Domain\Repository\ReglementBalanceRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * Lecture DBAL pure de reglement_balance — aucune écriture.
 */
final class DoctrineReglementBalanceRepository implements ReglementBalanceRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function findBalance(
        int $partyAccountId,
        string $partyRole,
        string $currencyCode,
    ): ?array {
        /** @var array{balance_minor: int|string, entry_count: int|string, last_entry_id: int|string|null, updated_at: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT balance_minor, entry_count, last_entry_id, updated_at
             FROM reglement_balance
             WHERE party_account_id = :party_account_id
               AND party_role = :party_role
               AND currency_code = :currency_code',
            [
                'party_account_id' => $partyAccountId,
                'party_role' => $partyRole,
                'currency_code' => strtoupper(trim($currencyCode)),
            ],
        );

        if ($row === false) {
            return null;
        }

        return $this->mapBalanceRow($row);
    }

    public function findAllBalancesForParty(int $partyAccountId): array
    {
        /** @var list<array{party_role: string, currency_code: string, balance_minor: int|string, entry_count: int|string, last_entry_id: int|string|null, updated_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT party_role, currency_code, balance_minor, entry_count, last_entry_id, updated_at
             FROM reglement_balance
             WHERE party_account_id = :party_account_id
             ORDER BY party_role ASC, currency_code ASC',
            ['party_account_id' => $partyAccountId],
        );

        $result = [];
        foreach ($rows as $row) {
            $mapped = $this->mapBalanceRow($row);
            $result[] = [
                'partyRole' => $row['party_role'],
                'currencyCode' => $row['currency_code'],
                'balanceMinor' => $mapped['balanceMinor'],
                'entryCount' => $mapped['entryCount'],
                'lastEntryId' => $mapped['lastEntryId'],
                'updatedAt' => $mapped['updatedAt'],
            ];
        }

        return $result;
    }

    /**
     * @param array{
     *     balance_minor: int|string,
     *     entry_count: int|string,
     *     last_entry_id: int|string|null,
     *     updated_at: string
     * } $row
     *
     * @return array{
     *     balanceMinor: int,
     *     entryCount: int,
     *     lastEntryId: int|null,
     *     updatedAt: DateTimeImmutable
     * }
     */
    private function mapBalanceRow(array $row): array
    {
        return [
            'balanceMinor' => $this->toInt($row['balance_minor']),
            'entryCount' => $this->toInt($row['entry_count']),
            'lastEntryId' => $row['last_entry_id'] === null ? null : $this->toInt($row['last_entry_id']),
            'updatedAt' => new DateTimeImmutable($row['updated_at']),
        ];
    }

    private function toInt(int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new RuntimeException('Expected numeric DBAL scalar.');
    }
}
