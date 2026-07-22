<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * PATCH sans aucun champ applicable (body vide / tous null).
 */
final class PartyAccountNoChangesException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account.no_changes_provided';
    }

    public static function create(): self
    {
        return new self('No changes provided for party account update.');
    }
}
