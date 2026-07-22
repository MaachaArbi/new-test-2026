<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingCancellationPolicy;
use App\Modules\Booking\Domain\Repository\BookingCancellationPolicyRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingCancellationPolicyRepository implements BookingCancellationPolicyRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?BookingCancellationPolicy
    {
        $found = $this->unitOfWork->find(BookingCancellationPolicy::class, $id);

        return $found instanceof BookingCancellationPolicy ? $found : null;
    }

    public function existsById(int $id): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_cancellation_policy WHERE id = :id',
            ['id' => $id],
        );

        return $raw !== false && $raw !== null;
    }

    public function findByBookingId(int $bookingId): ?BookingCancellationPolicy
    {
        /** @var BookingCancellationPolicy|null $policy */
        $policy = $this->unitOfWork->createQueryBuilder()
            ->select('policy')
            ->from(BookingCancellationPolicy::class, 'policy')
            ->andWhere('policy.bookingId = :bookingId')
            ->andWhere('policy.roomId IS NULL')
            ->setParameter('bookingId', $bookingId)
            ->getQuery()
            ->getOneOrNullResult();

        return $policy;
    }

    public function findByRoomId(int $roomId): ?BookingCancellationPolicy
    {
        /** @var BookingCancellationPolicy|null $policy */
        $policy = $this->unitOfWork->createQueryBuilder()
            ->select('policy')
            ->from(BookingCancellationPolicy::class, 'policy')
            ->andWhere('policy.roomId = :roomId')
            ->setParameter('roomId', $roomId)
            ->getQuery()
            ->getOneOrNullResult();

        return $policy;
    }

    public function existsForBooking(int $bookingId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_cancellation_policy
             WHERE booking_id = :bookingId AND room_id IS NULL',
            ['bookingId' => $bookingId],
        );

        return $raw !== false && $raw !== null;
    }

    public function existsForRoom(int $roomId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_cancellation_policy WHERE room_id = :roomId',
            ['roomId' => $roomId],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(BookingCancellationPolicy $policy): void
    {
        $this->unitOfWork->persist($policy);
    }
}
