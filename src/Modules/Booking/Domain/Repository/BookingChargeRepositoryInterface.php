<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingCharge;

interface BookingChargeRepositoryInterface
{
    /**
     * @return list<BookingCharge>
     */
    public function findByBookingId(int $bookingId): array;

    public function save(BookingCharge $charge): void;
}
