<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\ValueObject;

use App\Modules\Party\Domain\Exception\InvalidPartyRoleCodeException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\ValueObject\OpenReferentialCode;

/**
 * Code d'un rôle structurel (référentiel ouvert party_role, VARCHAR(30)).
 * Pas un enum PHP : de nouveaux codes (ex. franchise) s'ajoutent sans migration de code.
 */
final readonly class PartyRoleCode extends OpenReferentialCode
{
    protected static function maxLength(): int
    {
        return 30;
    }

    protected static function emptyException(): DomainException
    {
        return InvalidPartyRoleCodeException::empty();
    }

    protected static function tooLongException(string $value, int $maxLength): DomainException
    {
        return InvalidPartyRoleCodeException::tooLong($value, $maxLength);
    }
}
