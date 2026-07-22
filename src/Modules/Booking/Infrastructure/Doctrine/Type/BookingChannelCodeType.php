<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Doctrine\Type;

use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion BookingChannelCode ↔ VARCHAR(30).
 */
final class BookingChannelCodeType extends StringType
{
    public const NAME = 'booking_channel_code';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BookingChannelCode
    {
        return match (true) {
            $value === null => null,
            $value instanceof BookingChannelCode => $value,
            is_string($value) => BookingChannelCode::fromString($value),
            default => throw new ConversionException(sprintf(
                'Unsupported DB value for booking_channel_code: %s.',
                get_debug_type($value),
            )),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BookingChannelCode) {
            throw new ConversionException(
                'Only BookingChannelCode instances may be written to channel_code.',
            );
        }

        return $value->toString();
    }
}
