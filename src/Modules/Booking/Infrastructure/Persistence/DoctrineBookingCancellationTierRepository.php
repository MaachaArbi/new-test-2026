<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingCancellationTier;
use App\Modules\Booking\Domain\Repository\BookingCancellationTierRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineBookingCancellationTierRepository implements BookingCancellationTierRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * @return list<BookingCancellationTier>
     */
    public function findByPolicyId(int $policyId): array
    {
        /** @var list<BookingCancellationTier> $tiers */
        $tiers = $this->unitOfWork->createQueryBuilder()
            ->select('tier')
            ->from(BookingCancellationTier::class, 'tier')
            ->andWhere('tier.policyId = :policyId')
            ->setParameter('policyId', $policyId)
            ->orderBy('tier.sortOrder', 'ASC')
            ->addOrderBy('tier.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $tiers;
    }

    public function save(BookingCancellationTier $tier): void
    {
        $this->unitOfWork->persist($tier);
    }
}
