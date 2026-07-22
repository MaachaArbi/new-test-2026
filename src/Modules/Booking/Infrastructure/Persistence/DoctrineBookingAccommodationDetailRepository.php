<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingAccommodationDetail;
use App\Modules\Booking\Domain\Repository\BookingAccommodationDetailRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineBookingAccommodationDetailRepository implements BookingAccommodationDetailRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findByBookingId(int $bookingId): ?BookingAccommodationDetail
    {
        $found = $this->unitOfWork->find(
            BookingAccommodationDetail::class,
            $bookingId,
        );

        return $found instanceof BookingAccommodationDetail ? $found : null;
    }

    public function save(BookingAccommodationDetail $detail): void
    {
        $this->unitOfWork->persist($detail);
    }
}
