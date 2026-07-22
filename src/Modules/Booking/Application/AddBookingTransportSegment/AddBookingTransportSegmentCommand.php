<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingTransportSegment;

use DateTimeImmutable;

final readonly class AddBookingTransportSegmentCommand
{
    public function __construct(
        public int $bookingId,
        public DateTimeImmutable $departureAt,
        public DateTimeImmutable $arrivalAt,
        public int $sequenceNumber = 1,
        public ?string $carrierCode = null,
        public ?string $departureLocation = null,
        public ?string $arrivalLocation = null,
    ) {
    }
}
