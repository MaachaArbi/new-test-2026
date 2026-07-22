<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingHotelRoom;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingHotelRoomRepository implements BookingHotelRoomRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<BookingHotelRoom>
     */
    public function findByBookingId(int $bookingId): array
    {
        /** @var list<BookingHotelRoom> $rooms */
        $rooms = $this->unitOfWork->createQueryBuilder()
            ->select('room')
            ->from(BookingHotelRoom::class, 'room')
            ->andWhere('room.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('room.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $rooms;
    }

    public function belongsToBooking(int $roomId, int $bookingId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM booking_hotel_room WHERE id = :roomId AND booking_id = :bookingId',
            ['roomId' => $roomId, 'bookingId' => $bookingId],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(BookingHotelRoom $room): void
    {
        $this->unitOfWork->persist($room);
    }
}
