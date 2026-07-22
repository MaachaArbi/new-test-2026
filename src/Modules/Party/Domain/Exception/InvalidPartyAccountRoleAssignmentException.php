<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants sur PartyAccountRoleAssignment.
 */
final class InvalidPartyAccountRoleAssignmentException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_role.already_revoked';
    }

    public static function alreadyRevoked(int $accountId, string $roleCode): self
    {
        return new self(
            'Cannot revoke a party account role assignment that is already closed (valid_to set).',
            [
                'account_id' => $accountId,
                'role_code' => $roleCode,
            ],
        );
    }
}
