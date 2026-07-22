<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants sur PartyAccountFunctionAssignment.
 */
final class InvalidPartyAccountFunctionAssignmentException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_function.already_revoked';
    }

    public static function alreadyRevoked(
        int $personAccountId,
        int $organizationAccountId,
        string $functionCode,
    ): self {
        $context = [
            'function_code' => $functionCode,
            'organization_account_id' => $organizationAccountId,
            'person_account_id' => $personAccountId,
        ];

        return new self(
            'Cannot revoke a party account function assignment that is already closed (valid_to set).',
            $context,
        );
    }
}
