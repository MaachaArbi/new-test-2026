<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * room_id fourni n'appartient pas au booking_id de la politique.
 * Non couvert par la FK SQL (qui ne vérifie que l'existence de la room).
 */
final class BookingCancellationRoomMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_cancellation_policy.room_mismatch';
    }

    public static function forBookingAndRoom(int $bookingId, int $roomId): self
    {
        return new self(
            sprintf(
                'Hotel room %d does not belong to booking %d.',
                $roomId,
                $bookingId,
            ),
            [
                'booking_id' => $bookingId,
                'room_id' => $roomId,
            ],
        );
    }
}
