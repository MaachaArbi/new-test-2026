<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\SetBookingCarRentalDetail;

use DateTimeImmutable;

final readonly class SetBookingCarRentalDetailCommand
{
    public function __construct(
        public int $bookingId,
        public ?string $vehicleCategory = null,
        public ?string $vehicleBrandModel = null,
        public ?DateTimeImmutable $pickupAt = null,
        public ?DateTimeImmutable $dropoffAt = null,
        public ?string $pickupLocation = null,
        public ?string $dropoffLocation = null,
    ) {
    }
}
