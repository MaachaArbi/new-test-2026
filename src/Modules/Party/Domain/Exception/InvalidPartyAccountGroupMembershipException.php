<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants sur PartyAccountGroupMembership.
 */
final class InvalidPartyAccountGroupMembershipException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_group_member.already_revoked';
    }

    public static function alreadyRevoked(int $accountId, int $groupId): self
    {
        return new self(
            'Cannot revoke a party account group membership that is already closed (valid_to set).',
            [
                'account_id' => $accountId,
                'group_id' => $groupId,
            ],
        );
    }
}
