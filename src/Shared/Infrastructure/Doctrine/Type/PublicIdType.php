<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\PublicId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GuidType;

/**
 * Conversion PublicId (Domain) ↔ UUID PostgreSQL (ADR-018).
 */
final class PublicIdType extends GuidType
{
    public const NAME = 'public_id';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PublicId
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PublicId) {
            return $value;
        }

        $asString = parent::convertToPHPValue($value, $platform);
        if (!is_string($asString) || $asString === '') {
            throw new ConversionException(sprintf(
                'Invalid database value for %s (%s).',
                self::NAME,
                get_debug_type($value),
            ));
        }

        return PublicId::fromString($asString);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PublicId) {
            $uuid = $value->toString();
            $converted = parent::convertToDatabaseValue($uuid, $platform);

            return is_string($converted) ? $converted : $uuid;
        }

        throw new ConversionException(sprintf(
            'Expected %s for column type %s, got %s.',
            PublicId::class,
            self::NAME,
            get_debug_type($value),
        ));
    }
}
