<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\RevokePartyAccountGroupMembership;

/**
 * Commande de révocation d'une membership de groupe précise (par id de ligne).
 */
final readonly class RevokePartyAccountGroupMembershipCommand
{
    public function __construct(
        public int $membershipId,
    ) {
    }
}
