<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Un booking ne peut avoir qu'un seul is_pax_leader = true
 * (uq_booking_traveler_pax_leader).
 */
final class BookingTravelerPaxLeaderAlreadySetException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_traveler.pax_leader_already_set';
    }

    public static function forBooking(int $bookingId): self
    {
        return new self(
            sprintf('Booking %d already has a pax leader traveler.', $bookingId),
            ['booking_id' => $bookingId],
        );
    }
}
