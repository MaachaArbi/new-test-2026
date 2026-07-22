<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\TransitionBookingStatus;

/**
 * Transition de status_code du pivot booking.
 * Séparé du workflow flags (UpdateBookingWorkflow) — champ structurant.
 */
final readonly class TransitionBookingStatusCommand
{
    public function __construct(
        public int $bookingId,
        public string $statusCode,
    ) {
    }
}
