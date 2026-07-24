<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\SetBookingAccommodationDetail;

final readonly class SetBookingAccommodationDetailCommand
{
    public function __construct(
        public int $bookingId,
        public ?int $accommodationId = null,
        public ?string $accommodationNameSnapshot = null,
        public ?string $boardTypeSnapshot = null,
    ) {
    }
}
