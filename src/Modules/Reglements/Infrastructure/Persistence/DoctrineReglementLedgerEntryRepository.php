<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Persistence;

use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

/**
 * append() = UnitOfWork::persist (INSERT). Pas de save/update.
 * sumActiveByBook = DBAL (ADR-003).
 */
final class DoctrineReglementLedgerEntryRepository implements ReglementLedgerEntryRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?ReglementLedgerEntry
    {
        /** @var ReglementLedgerEntry|null $entry */
        $entry = $this->unitOfWork->find(ReglementLedgerEntry::class, $id);

        return $entry;
    }

    public function findByPublicId(PublicId $publicId): ?ReglementLedgerEntry
    {
        /** @var ReglementLedgerEntry|null $entry */
        $entry = $this->unitOfWork->createQueryBuilder()
            ->select('e')
            ->from(ReglementLedgerEntry::class, 'e')
            ->andWhere('e.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $entry;
    }

    public function findByBookingId(int $bookingId): array
    {
        /** @var list<ReglementLedgerEntry> $entries */
        $entries = $this->unitOfWork->createQueryBuilder()
            ->select('e')
            ->from(ReglementLedgerEntry::class, 'e')
            ->andWhere('e.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $entries;
    }

    public function append(ReglementLedgerEntry $entry): void
    {
        $this->unitOfWork->persist($entry);
    }

    public function sumActiveByBook(
        int $partyAccountId,
        InstrumentPartyRole $partyRole,
        string $currencyCode,
    ): int {
        $raw = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(amount_minor), 0)
             FROM reglement_ledger_entry
             WHERE party_account_id = :party_account_id
               AND party_role = :party_role
               AND currency_code = :currency_code',
            [
                'party_account_id' => $partyAccountId,
                'party_role' => $partyRole->value,
                'currency_code' => strtoupper(trim($currencyCode)),
            ],
        );

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return 0;
    }
}
