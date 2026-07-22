<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\PostReglementObligationFromBooking;

/**
 * Projection d'obligations client depuis booking_payer_split actifs.
 * Lecture Booking uniquement — jamais d'écriture vers Booking.
 */
final readonly class PostReglementObligationFromBookingCommand
{
    public function __construct(
        public int $bookingId,
        public ?int $createdBy = null,
    ) {
    }
}
