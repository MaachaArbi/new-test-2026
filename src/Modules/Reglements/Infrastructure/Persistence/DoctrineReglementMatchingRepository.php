<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Persistence;

use App\Modules\Reglements\Domain\Entity\ReglementMatching;
use App\Modules\Reglements\Domain\Repository\ReglementMatchingRepositoryInterface;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineReglementMatchingRepository implements ReglementMatchingRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?ReglementMatching
    {
        /** @var ReglementMatching|null $matching */
        $matching = $this->unitOfWork->find(ReglementMatching::class, $id);

        return $matching;
    }

    public function findByPublicId(PublicId $publicId): ?ReglementMatching
    {
        /** @var ReglementMatching|null $matching */
        $matching = $this->unitOfWork->createQueryBuilder()
            ->select('m')
            ->from(ReglementMatching::class, 'm')
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

    public function match(ReglementMatching $matching): void
    {
        $this->unitOfWork->persist($matching);
    }

    public function unmatch(ReglementMatching $matching): void
    {
        // dirty-checking Doctrine — commit = Handler.
    }

    private function sumActive(int $entryId, string $column): int
    {
        $sql = match ($column) {
            'credit_entry_id' => 'SELECT COALESCE(SUM(matched_amount_minor), 0)
                 FROM reglement_matching
                 WHERE credit_entry_id = :entry_id
                   AND unmatched_at IS NULL',
            'debit_entry_id' => 'SELECT COALESCE(SUM(matched_amount_minor), 0)
                 FROM reglement_matching
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
