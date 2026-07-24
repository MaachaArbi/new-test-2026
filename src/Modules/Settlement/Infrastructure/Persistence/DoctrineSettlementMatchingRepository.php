<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Persistence;

use App\Modules\Settlement\Domain\Entity\SettlementMatching;
use App\Modules\Settlement\Domain\Repository\SettlementMatchingRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineSettlementMatchingRepository implements SettlementMatchingRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?SettlementMatching
    {
        /** @var SettlementMatching|null $matching */
        $matching = $this->unitOfWork->find(SettlementMatching::class, $id);

        return $matching;
    }

    public function findByPublicId(PublicId $publicId): ?SettlementMatching
    {
        /** @var SettlementMatching|null $matching */
        $matching = $this->unitOfWork->createQueryBuilder()
            ->select('m')
            ->from(SettlementMatching::class, 'm')
            ->andWhere('m.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $matching;
    }

    public function sumActiveMatchedForCreditEntry(int $creditEntryId): int
    {
        return $this->sumActive($creditEntryId, 'credit_entry_id');
    }

    public function sumActiveMatchedForDebitEntry(int $debitEntryId): int
    {
        return $this->sumActive($debitEntryId, 'debit_entry_id');
    }

    public function match(SettlementMatching $matching): void
    {
        $this->unitOfWork->persist($matching);
    }

    public function unmatch(SettlementMatching $matching): void
    {
        // dirty-checking Doctrine — commit = Handler.
    }

    private function sumActive(int $entryId, string $column): int
    {
        $sql = match ($column) {
            'credit_entry_id' => 'SELECT COALESCE(SUM(matched_amount_minor), 0)
                 FROM settlement_matching
                 WHERE credit_entry_id = :entry_id
                   AND unmatched_at IS NULL',
            'debit_entry_id' => 'SELECT COALESCE(SUM(matched_amount_minor), 0)
                 FROM settlement_matching
                 WHERE debit_entry_id = :entry_id
                   AND unmatched_at IS NULL',
            default => null,
        };

        if ($sql === null) {
            return 0;
        }

        $raw = $this->connection->fetchOne($sql, ['entry_id' => $entryId]);

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return 0;
    }
}
