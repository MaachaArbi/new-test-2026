<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\AssignPartyAccountGroupMembership;

/**
 * Commande d'assignation d'un compte à un groupe.
 */
final readonly class AssignPartyAccountGroupMembershipCommand
{
    public function __construct(
        public int $accountId,
        public int $groupId,
        public ?int $createdBy,
    ) {
    }
}
