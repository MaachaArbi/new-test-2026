<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\ValueObject;

use App\Modules\Party\Domain\Exception\InvalidPartyFunctionCodeException;
use App\Shared\Domain\Exception\DomainException;
use App\Shared\Domain\ValueObject\OpenReferentialCode;

/**
 * Code d'une fonction métier (référentiel ouvert party_function, VARCHAR(30)).
 * Pas un enum PHP : de nouveaux codes s'ajoutent sans migration de code.
 * Inclut la fonction générique 'member' (décision #14 — ex-accès générique).
 */
final readonly class PartyFunctionCode extends OpenReferentialCode
{
    protected static function maxLength(): int
    {
        return 30;
    }

    protected static function emptyException(): DomainException
    {
        return InvalidPartyFunctionCodeException::empty();
    }

    protected static function tooLongException(string $value, int $maxLength): DomainException
    {
        return InvalidPartyFunctionCodeException::tooLong($value, $maxLength);
    }
}
