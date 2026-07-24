<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Repository;

use DateTimeImmutable;

/**
 * Lecture pure du snapshot `settlement_balance` (maintenu par trigger).
 * Aucune écriture — jamais.
 *
 * @phpstan-type BalanceRow array{
 *     balanceMinor: int,
 *     entryCount: int,
 *     lastEntryId: int|null,
 *     updatedAt: DateTimeImmutable
 * }
 * @phpstan-type BalanceBookRow array{
 *     partyRole: string,
 *     currencyCode: string,
 *     balanceMinor: int,
 *     entryCount: int,
 *     lastEntryId: int|null,
 *     updatedAt: DateTimeImmutable
 * }
 */
interface SettlementBalanceRepositoryInterface
{
    /**
     * @return BalanceRow|null
     */
    public function findBalance(
        int $partyAccountId,
        string $partyRole,
        string $currencyCode,
    ): ?array;

    /**
     * Tous les livres (rôle × devise) d'un compte.
     *
     * @return list<BalanceBookRow>
     */
    public function findAllBalancesForParty(int $partyAccountId): array;
}
