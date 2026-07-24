<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Persistence;

use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

/**
 * append() = UnitOfWork::persist (INSERT). Pas de save/update.
 * sumActiveByBook = DBAL (ADR-003).
 */
final class DoctrineSettlementLedgerEntryRepository implements SettlementLedgerEntryRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?SettlementLedgerEntry
    {
        /** @var SettlementLedgerEntry|null $entry */
        $entry = $this->unitOfWork->find(SettlementLedgerEntry::class, $id);

        return $entry;
    }

    public function findByPublicId(PublicId $publicId): ?SettlementLedgerEntry
    {
        /** @var SettlementLedgerEntry|null $entry */
        $entry = $this->unitOfWork->createQueryBuilder()
            ->select('e')
            ->from(SettlementLedgerEntry::class, 'e')
            ->andWhere('e.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $entry;
    }

    public function findByBookingId(int $bookingId): array
    {
        /** @var list<SettlementLedgerEntry> $entries */
        $entries = $this->unitOfWork->createQueryBuilder()
            ->select('e')
            ->from(SettlementLedgerEntry::class, 'e')
            ->andWhere('e.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $entries;
    }

    public function append(SettlementLedgerEntry $entry): void
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
             FROM settlement_ledger_entry
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
