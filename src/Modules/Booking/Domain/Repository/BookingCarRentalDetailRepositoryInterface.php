<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingCarRentalDetail;

interface BookingCarRentalDetailRepositoryInterface
{
    public function findByBookingId(int $bookingId): ?BookingCarRentalDetail;

    public function save(BookingCarRentalDetail $detail): void;
}
