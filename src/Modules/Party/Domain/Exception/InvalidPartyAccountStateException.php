<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants d'état sur l'agrégat PartyAccount.
 */
final class InvalidPartyAccountStateException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account.parent_account_not_allowed_for_person';
    }

    public static function parentAccountNotAllowedForPerson(
        int $attemptedParentId,
        string $displayName,
    ): self {
        return new self(
            'parentAccountId is only allowed for organization accounts (schema note #3).',
            [
                'attempted_parent_id' => $attemptedParentId,
                'display_name' => $displayName,
            ],
        );
    }
}
