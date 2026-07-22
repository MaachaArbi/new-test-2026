<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Une seule politique "toute réservation" par booking, une seule par room.
 * Miroir des index partiels uq_booking_cancellation_policy_*.
 */
final class BookingCancellationPolicyAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_cancellation_policy.already_exists';
    }

    public static function forBooking(int $bookingId): self
    {
        return new self(
            sprintf('A whole-booking cancellation policy already exists for booking %d.', $bookingId),
            ['booking_id' => $bookingId],
        );
    }

    public static function forRoom(int $roomId): self
    {
        return new self(
            sprintf('A cancellation policy already exists for room %d.', $roomId),
            ['room_id' => $roomId],
        );
    }
}
