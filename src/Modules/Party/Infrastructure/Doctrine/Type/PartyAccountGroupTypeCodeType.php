<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Doctrine\Type;

use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;

/**
 * Conversion PartyAccountGroupTypeCode ↔ VARCHAR(30).
 */
final class PartyAccountGroupTypeCodeType extends StringType
{
    public const NAME = 'party_account_group_type_code';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PartyAccountGroupTypeCode
    {
        return match (true) {
            $value === null => null,
            $value instanceof PartyAccountGroupTypeCode => $value,
            is_string($value) => PartyAccountGroupTypeCode::fromString($value),
            default => throw new ConversionException(sprintf(
                'Unsupported DB value for party_account_group_type_code: %s.',
                get_debug_type($value),
            )),
        };
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return match (true) {
            $value === null => null,
            $value instanceof PartyAccountGroupTypeCode => $value->toString(),
            default => throw new ConversionException(
                'Only PartyAccountGroupTypeCode may be written to group_type_code.',
            ),
        };
    }
}
