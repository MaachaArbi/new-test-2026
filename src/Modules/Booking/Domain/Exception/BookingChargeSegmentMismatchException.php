<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * segment_id fourni n'appartient pas au booking_id de la charge.
 */
final class BookingChargeSegmentMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_charge.segment_mismatch';
    }

    public static function forBookingAndSegment(int $bookingId, int $segmentId): self
    {
        return new self(
            sprintf('Transport segment %d does not belong to booking %d.', $segmentId, $bookingId),
            [
                'booking_id' => $bookingId,
                'segment_id' => $segmentId,
            ],
        );
    }
}
