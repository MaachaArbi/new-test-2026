<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Doctrine\Type;

use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion BookingServiceTypeCode ↔ VARCHAR(30).
 */
final class BookingServiceTypeCodeType extends StringType
{
    public const NAME = 'booking_service_type_code';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BookingServiceTypeCode
    {
        return match (true) {
            $value === null => null,
            $value instanceof BookingServiceTypeCode => $value,
            is_string($value) => BookingServiceTypeCode::fromString($value),
            default => throw new ConversionException(sprintf(
                'Unsupported DB value for booking_service_type_code: %s.',
                get_debug_type($value),
            )),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BookingServiceTypeCode) {
            throw new ConversionException(
                'Only BookingServiceTypeCode instances may be written to service_type_code.',
            );
        }

        return $value->toString();
    }
}
