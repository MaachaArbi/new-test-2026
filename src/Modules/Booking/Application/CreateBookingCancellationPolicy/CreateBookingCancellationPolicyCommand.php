<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBookingCancellationPolicy;

final readonly class CreateBookingCancellationPolicyCommand
{
    public function __construct(
        public int $bookingId,
        public ?int $roomId = null,
    ) {
    }
}
