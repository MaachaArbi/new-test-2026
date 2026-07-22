<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\Exception\InvalidBookingChannelCodeException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\ValueObject\OpenReferentialCode;

/**
 * Code d'un canal de création (référentiel ouvert booking_channel, VARCHAR(30)).
 */
final readonly class BookingChannelCode extends OpenReferentialCode
{
    protected static function maxLength(): int
    {
        return 30;
    }

    protected static function emptyException(): DomainException
    {
        return InvalidBookingChannelCodeException::empty();
    }

    protected static function tooLongException(string $value, int $maxLength): DomainException
    {
        return InvalidBookingChannelCodeException::tooLong($value, $maxLength);
    }
}
