<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\Exception\InvalidBookingStatusCodeException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\ValueObject\OpenReferentialCode;

/**
 * Code d'un statut de réservation (référentiel ouvert booking_status, VARCHAR(30)).
 */
final readonly class BookingStatusCode extends OpenReferentialCode
{
    protected static function maxLength(): int
    {
        return 30;
    }

    protected static function emptyException(): DomainException
    {
        return InvalidBookingStatusCodeException::empty();
    }

    protected static function tooLongException(string $value, int $maxLength): DomainException
    {
        return InvalidBookingStatusCodeException::tooLong($value, $maxLength);
    }
}
