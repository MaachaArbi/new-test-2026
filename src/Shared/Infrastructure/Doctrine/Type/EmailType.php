<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion Email (Shared Domain) ↔ VARCHAR nullable.
 */
final class EmailType extends StringType
{
    public const NAME = 'email';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Email
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Email) {
            return $value;
        }

        $raw = parent::convertToPHPValue($value, $platform);
        if (!is_string($raw)) {
            throw new ConversionException(sprintf(
                'Email DB column must hydrate to string, got %s.',
                get_debug_type($value),
            ));
        }

        return Email::fromString($raw);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return match (true) {
            $value === null => null,
            $value instanceof Email => parent::convertToDatabaseValue($value->toString(), $platform),
            default => throw new ConversionException(sprintf(
                'Email column expects %s, received %s.',
                Email::class,
                get_debug_type($value),
            )),
        };
    }
}
