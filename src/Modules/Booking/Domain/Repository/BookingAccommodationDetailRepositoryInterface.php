<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingAccommodationDetail;

interface BookingAccommodationDetailRepositoryInterface
{
    public function findByBookingId(int $bookingId): ?BookingAccommodationDetail;

    public function save(BookingAccommodationDetail $detail): void;
}
