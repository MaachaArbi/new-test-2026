<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingCarRentalDetail;
use App\Modules\Booking\Domain\Repository\BookingCarRentalDetailRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineBookingCarRentalDetailRepository implements BookingCarRentalDetailRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByBookingId(int $bookingId): ?BookingCarRentalDetail
    {
        $found = $this->unitOfWork->find(
            BookingCarRentalDetail::class,
            $bookingId,
        );

        return $found instanceof BookingCarRentalDetail ? $found : null;
    }

    public function save(BookingCarRentalDetail $detail): void
    {
        $this->unitOfWork->persist($detail);
    }
}
