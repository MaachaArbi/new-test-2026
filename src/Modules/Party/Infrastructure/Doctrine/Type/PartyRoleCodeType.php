<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Doctrine\Type;

use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion PartyRoleCode ↔ VARCHAR(30) (référentiel ouvert party_role).
 */
final class PartyRoleCodeType extends StringType
{
    public const NAME = 'party_role_code';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PartyRoleCode
    {
        return match (true) {
            $value === null => null,
            $value instanceof PartyRoleCode => $value,
            is_string($value) => PartyRoleCode::fromString($value),
            default => throw new ConversionException(sprintf(
                'Unsupported DB value for party_role_code: %s.',
                get_debug_type($value),
            )),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof PartyRoleCode) {
            throw new ConversionException(
                'Only PartyRoleCode instances may be written to role_code.',
            );
        }

        return $value->toString();
    }
}
