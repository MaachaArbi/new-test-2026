<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingTraveler;

interface BookingTravelerRepositoryInterface
{
    /**
     * @return list<BookingTraveler>
     */
    public function findByBookingId(int $bookingId): array;

    /** ADR-003 : existence ciblée (DBAL), pas de chargement de collection. */
    public function belongsToBooking(int $travelerId, int $bookingId): bool;

    public function hasActivePaxLeader(int $bookingId): bool;

    public function save(BookingTraveler $traveler): void;
}
