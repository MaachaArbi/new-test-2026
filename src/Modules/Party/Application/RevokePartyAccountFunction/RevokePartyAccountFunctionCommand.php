<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\RevokePartyAccountFunction;

/**
 * Commande de révocation d'une assignation de fonction précise (par id de ligne).
 */
final readonly class RevokePartyAccountFunctionCommand
{
    public function __construct(
        public int $assignmentId,
    ) {
    }
}
