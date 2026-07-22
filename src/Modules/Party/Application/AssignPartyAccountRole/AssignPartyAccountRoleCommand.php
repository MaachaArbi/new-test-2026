<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\AssignPartyAccountRole;

/**
 * Commande d'assignation d'un rôle à un party_account.
 */
final readonly class AssignPartyAccountRoleCommand
{
    public function __construct(
        public int $accountId,
        public string $roleCode,
        public ?int $createdBy,
    ) {
    }
}
