<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingSettlement;
use App\Modules\Booking\Domain\Repository\BookingSettlementRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingSettlementRepository implements BookingSettlementRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?BookingSettlement
    {
        $found = $this->unitOfWork->find(BookingSettlement::class, $id);

        return $found instanceof BookingSettlement ? $found : null;
    }

    /**
     * @return list<BookingSettlement>
     */
    public function findByBookingId(int $bookingId, bool $activeOnly = true): array
    {
        $qb = $this->unitOfWork->createQueryBuilder()
            ->select('settlement')
            ->from(BookingSettlement::class, 'settlement')
            ->andWhere('settlement.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('settlement.id', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('settlement.validTo IS NULL');
        }

        /** @var list<BookingSettlement> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    public function hasActiveSettlement(
        int $bookingId,
        BeneficiaryRole $beneficiaryRole,
        int $beneficiaryAccountId,
    ): bool {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_settlement
             WHERE booking_id = :bookingId
               AND beneficiary_role = :role
               AND beneficiary_account_id = :accountId
               AND valid_to IS NULL
             LIMIT 1',
            [
                'bookingId' => $bookingId,
                'role' => $beneficiaryRole->value,
                'accountId' => $beneficiaryAccountId,
            ],
        );

        return $raw !== false && $raw !== null;
    }

    public function assign(BookingSettlement $settlement): void
    {
        $this->unitOfWork->persist($settlement);
    }

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     *
     * Commit (flush) is the caller's responsibility.
     */
    public function revoke(BookingSettlement $settlement): void
    {
    }
}
