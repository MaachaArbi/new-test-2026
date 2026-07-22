<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingTraveler;
use App\Modules\Booking\Domain\Repository\BookingTravelerRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingTravelerRepository implements BookingTravelerRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<BookingTraveler>
     */
    public function findByBookingId(int $bookingId): array
    {
        /** @var list<BookingTraveler> $travelers */
        $travelers = $this->unitOfWork->createQueryBuilder()
            ->select('traveler')
            ->from(BookingTraveler::class, 'traveler')
            ->andWhere('traveler.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('traveler.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $travelers;
    }

    public function belongsToBooking(int $travelerId, int $bookingId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_traveler WHERE id = :travelerId AND booking_id = :bookingId',
            ['travelerId' => $travelerId, 'bookingId' => $bookingId],
        );

        return $raw !== false && $raw !== null;
    }

    public function hasActivePaxLeader(int $bookingId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_traveler
             WHERE booking_id = :bookingId AND is_pax_leader = true
             LIMIT 1',
            ['bookingId' => $bookingId],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(BookingTraveler $traveler): void
    {
        $this->unitOfWork->persist($traveler);
    }
}
