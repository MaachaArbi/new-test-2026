<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Extension demandée non autorisée pour le service_type_code du booking
 * (référentiel booking_service_type_extension).
 */
final class BookingServiceTypeMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.service_type_mismatch';
    }

    public static function forBooking(
        int $bookingId,
        string $extensionCode,
        string $actualServiceType,
    ): self {
        return new self(
            sprintf(
                'Booking %d service_type "%s" is not allowed for extension "%s".',
                $bookingId,
                $actualServiceType,
                $extensionCode,
            ),
            [
                'booking_id' => $bookingId,
                'extension_code' => $extensionCode,
                'actual_service_type' => $actualServiceType,
            ],
        );
    }
}
