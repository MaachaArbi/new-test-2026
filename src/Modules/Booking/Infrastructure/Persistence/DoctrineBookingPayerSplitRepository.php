<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingPayerSplit;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingPayerSplitRepository implements BookingPayerSplitRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?BookingPayerSplit
    {
        $found = $this->unitOfWork->find(BookingPayerSplit::class, $id);
        if (!$found instanceof BookingPayerSplit) {
            return null;
        }

        $currency = $this->bookingVenteCurrency($found->bookingId());
        if ($currency !== null) {
            $found->hydrateCurrency($currency);
        }

        return $found;
    }

    /**
     * @return list<BookingPayerSplit>
     */
    public function findByBookingId(int $bookingId, bool $activeOnly = true): array
    {
        $qb = $this->unitOfWork->createQueryBuilder()
            ->select('split')
            ->from(BookingPayerSplit::class, 'split')
            ->andWhere('split.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('split.id', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('split.validTo IS NULL');
        }

        /** @var list<BookingPayerSplit> $rows */
        $rows = $qb->getQuery()->getResult();

        $currency = $this->bookingVenteCurrency($bookingId);
        if ($currency !== null) {
            foreach ($rows as $row) {
                $row->hydrateCurrency($currency);
            }
        }

        return $rows;
    }

    public function sumActiveAmountForBooking(int $bookingId): int
    {
        $raw = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(amount), 0)
             FROM booking_payer_split
             WHERE booking_id = :bookingId
               AND valid_to IS NULL',
            ['bookingId' => $bookingId],
        );

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return 0;
    }

    public function hasActivePayerSplit(int $bookingId, int $payerAccountId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_payer_split
             WHERE booking_id = :bookingId
               AND payer_account_id = :payerAccountId
               AND valid_to IS NULL
             LIMIT 1',
            [
                'bookingId' => $bookingId,
                'payerAccountId' => $payerAccountId,
            ],
        );

        return $raw !== false && $raw !== null;
    }

    public function assign(BookingPayerSplit $split): void
    {
        $this->unitOfWork->persist($split);
    }

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     *
     * Commit (flush) is the caller's responsibility.
     */
    public function revoke(BookingPayerSplit $split): void
    {
    }

    private function bookingVenteCurrency(int $bookingId): ?string
    {
        /** @var string|false|null $raw */
        $raw = $this->connection->fetchOne(
            'SELECT vente_currency_code FROM booking WHERE id = :id',
            ['id' => $bookingId],
        );

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return $raw;
    }
}
