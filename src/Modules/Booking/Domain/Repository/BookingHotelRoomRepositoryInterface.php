<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingHotelRoom;

interface BookingHotelRoomRepositoryInterface
{
    /**
     * @return list<BookingHotelRoom>
     */
    public function findByBookingId(int $bookingId): array;

    /** ADR-003 : existence ciblée (DBAL), pas de chargement de collection. */
    public function belongsToBooking(int $roomId, int $bookingId): bool;

    public function save(BookingHotelRoom $room): void;
}
