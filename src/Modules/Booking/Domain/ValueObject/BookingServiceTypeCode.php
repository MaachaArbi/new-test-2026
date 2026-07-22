<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\Exception\InvalidBookingServiceTypeCodeException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\ValueObject\OpenReferentialCode;

/**
 * Code d'un type de service (référentiel ouvert booking_service_type, VARCHAR(30)).
 */
final readonly class BookingServiceTypeCode extends OpenReferentialCode
{
    protected static function maxLength(): int
    {
        return 30;
    }

    protected static function emptyException(): DomainException
    {
        return InvalidBookingServiceTypeCodeException::empty();
    }

    protected static function tooLongException(string $value, int $maxLength): DomainException
    {
        return InvalidBookingServiceTypeCodeException::tooLong($value, $maxLength);
    }
}
