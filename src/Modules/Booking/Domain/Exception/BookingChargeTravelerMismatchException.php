<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * traveler_id fourni n'appartient pas au booking_id de la charge.
 */
final class BookingChargeTravelerMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_charge.traveler_mismatch';
    }

    public static function forBookingAndTraveler(int $bookingId, int $travelerId): self
    {
        return new self(
            sprintf('Traveler %d does not belong to booking %d.', $travelerId, $bookingId),
            [
                'booking_id' => $bookingId,
                'traveler_id' => $travelerId,
            ],
        );
    }
}
