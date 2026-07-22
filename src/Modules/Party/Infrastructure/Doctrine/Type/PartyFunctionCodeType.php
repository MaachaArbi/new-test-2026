<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Doctrine\Type;

use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion PartyFunctionCode ↔ VARCHAR(30) (référentiel ouvert party_function).
 */
final class PartyFunctionCodeType extends StringType
{
    public const NAME = 'party_function_code';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PartyFunctionCode
    {
        if ($value instanceof PartyFunctionCode) {
            return $value;
        }

        if (is_string($value)) {
            return PartyFunctionCode::fromString($value);
        }

        if ($value === null) {
            return null;
        }

        throw new ConversionException(sprintf(
            'Cannot map database value of type %s to PartyFunctionCode.',
            get_debug_type($value),
        ));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof PartyFunctionCode => $value->toString(),
            default => throw new ConversionException(
                'function_code column accepts PartyFunctionCode only.',
            ),
        };
    }
}
