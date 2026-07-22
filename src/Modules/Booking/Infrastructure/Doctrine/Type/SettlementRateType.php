<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Doctrine\Type;

use App\Modules\Booking\Domain\ValueObject\SettlementRate;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Conversion SettlementRate ↔ NUMERIC(6,3) (string, jamais float).
 */
final class SettlementRateType extends Type
{
    public const NAME = 'settlement_rate';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['precision'] = $column['precision'] ?? 6;
        $column['scale'] = $column['scale'] ?? 3;

        return $platform->getDecimalTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SettlementRate
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SettlementRate) {
            return $value;
        }

        if (is_float($value)) {
            throw new ConversionException(
                'SettlementRate DB column must not hydrate from float (precision loss).',
            );
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            throw new ConversionException(sprintf(
                'SettlementRate DB column must hydrate to string, got %s.',
                get_debug_type($value),
            ));
        }

        return SettlementRate::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return match (true) {
            $value === null => null,
            $value instanceof SettlementRate => $value->toString(),
            default => throw new ConversionException(sprintf(
                'SettlementRate column expects %s, received %s.',
                SettlementRate::class,
                get_debug_type($value),
            )),
        };
    }
}
