<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\SetBookingAccommodationDetail;

use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Domain\Entity\BookingAccommodationDetail;
use App\Modules\Booking\Domain\Repository\BookingAccommodationDetailRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : poser l'extension 1-1 hébergement (extension accommodation).
 */
final class SetBookingAccommodationDetailHandler
{
    private const REQUIRED_EXTENSION = 'accommodation';

    public function __construct(
        private readonly AssertBookingServiceType $assertBookingServiceType,
        private readonly BookingAccommodationDetailRepositoryInterface $detailRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(SetBookingAccommodationDetailCommand $command): BookingAccommodationDetail
    {
        ($this->assertBookingServiceType)($command->bookingId, self::REQUIRED_EXTENSION);

        $detail = BookingAccommodationDetail::create(
            $command->bookingId,
            $command->accommodationId,
            $command->accommodationNameSnapshot,
            $command->boardTypeSnapshot,
        );

        $this->detailRepository->save($detail);
        $this->unitOfWork->commit();

        return $detail;
    }
}
