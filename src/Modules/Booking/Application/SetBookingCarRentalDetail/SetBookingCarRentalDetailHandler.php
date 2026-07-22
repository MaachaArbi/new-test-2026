<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\SetBookingCarRentalDetail;

use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Domain\Entity\BookingCarRentalDetail;
use App\Modules\Booking\Domain\Repository\BookingCarRentalDetailRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : poser l'extension 1-1 location de voiture (extension car_rental).
 */
final class SetBookingCarRentalDetailHandler
{
    private const REQUIRED_EXTENSION = 'car_rental';

    public function __construct(
        private readonly AssertBookingServiceType $assertBookingServiceType,
        private readonly BookingCarRentalDetailRepositoryInterface $detailRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(SetBookingCarRentalDetailCommand $command): BookingCarRentalDetail
    {
        ($this->assertBookingServiceType)($command->bookingId, self::REQUIRED_EXTENSION);

        $detail = BookingCarRentalDetail::create(
            $command->bookingId,
            $command->vehicleCategory,
            $command->vehicleBrandModel,
            $command->pickupAt,
            $command->dropoffAt,
            $command->pickupLocation,
            $command->dropoffLocation,
        );

        $this->detailRepository->save($detail);
        $this->unitOfWork->commit();

        return $detail;
    }
}
