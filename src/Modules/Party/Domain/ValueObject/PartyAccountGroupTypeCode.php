<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\ValueObject;

use App\Modules\Party\Domain\Exception\InvalidPartyAccountGroupTypeCodeException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\ValueObject\OpenReferentialCode;

/**
 * Code d'une dimension de groupe (référentiel ouvert party_account_group_type, VARCHAR(30)).
 * Pas un enum PHP — 3ᵉ cas réel de VO string ouvert.
 */
final readonly class PartyAccountGroupTypeCode extends OpenReferentialCode
{
    protected static function maxLength(): int
    {
        return 30;
    }

    protected static function emptyException(): DomainException
    {
        return InvalidPartyAccountGroupTypeCodeException::empty();
    }

    protected static function tooLongException(string $value, int $maxLength): DomainException
    {
        return InvalidPartyAccountGroupTypeCodeException::tooLong($value, $maxLength);
    }
}
