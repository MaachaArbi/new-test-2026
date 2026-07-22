<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Membership de groupe introuvable (lookup Application avant revoke).
 */
final class PartyAccountGroupMembershipNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_group_member.membership_not_found';
    }

    public static function forId(int $membershipId): self
    {
        return new self(
            sprintf('Party account group membership %d not found.', $membershipId),
            ['membership_id' => $membershipId],
        );
    }
}
