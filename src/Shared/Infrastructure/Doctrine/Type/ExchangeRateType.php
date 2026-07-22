<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\ExchangeRate;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Conversion ExchangeRate (Shared Domain) ↔ NUMERIC (string, jamais float).
 * Étend Type (pas DecimalType) : signature convertToPHPValue parent fixe ?string.
 */
final class ExchangeRateType extends Type
{
    public const NAME = 'exchange_rate';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ExchangeRate
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ExchangeRate) {
            return $value;
        }

        if (is_float($value)) {
            throw new ConversionException(
                'ExchangeRate DB column must not hydrate from float (precision loss).',
            );
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            throw new ConversionException(sprintf(
                'ExchangeRate DB column must hydrate to string, got %s.',
                get_debug_type($value),
            ));
        }

        return ExchangeRate::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return match (true) {
            $value === null => null,
            $value instanceof ExchangeRate => $value->toString(),
            default => throw new ConversionException(sprintf(
                'ExchangeRate column expects %s, received %s.',
                ExchangeRate::class,
                get_debug_type($value),
            )),
        };
    }
}
