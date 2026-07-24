<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\PostSettlementObligationFromBooking;

/**
 * Projection d'obligations client depuis booking_payer_split actifs.
 * Lecture Booking uniquement — jamais d'écriture vers Booking.
 */
final readonly class PostSettlementObligationFromBookingCommand
{
    public function __construct(
        public int $bookingId,
        public ?int $createdBy = null,
    ) {
    }
}
