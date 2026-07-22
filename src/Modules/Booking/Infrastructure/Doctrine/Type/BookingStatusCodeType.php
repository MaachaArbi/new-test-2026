<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Doctrine\Type;

use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion BookingStatusCode ↔ VARCHAR(30).
 */
final class BookingStatusCodeType extends StringType
{
    public const NAME = 'booking_status_code';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BookingStatusCode
    {
        return match (true) {
            $value === null => null,
            $value instanceof BookingStatusCode => $value,
            is_string($value) => BookingStatusCode::fromString($value),
            default => throw new ConversionException(sprintf(
                'Unsupported DB value for booking_status_code: %s.',
                get_debug_type($value),
            )),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BookingStatusCode) {
            throw new ConversionException(
                'Only BookingStatusCode instances may be written to status_code.',
            );
        }

        return $value->toString();
    }
}
