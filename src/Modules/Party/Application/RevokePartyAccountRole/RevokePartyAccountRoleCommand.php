<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\RevokePartyAccountRole;

/**
 * Commande de révocation d'une assignation de rôle précise (par id de ligne).
 */
final readonly class RevokePartyAccountRoleCommand
{
    public function __construct(
        public int $assignmentId,
    ) {
    }
}
