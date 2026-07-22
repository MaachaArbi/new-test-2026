<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\AssignPartyAccountFunction;

/**
 * Commande d'assignation d'une fonction à une personne dans une organisation.
 */
final readonly class AssignPartyAccountFunctionCommand
{
    public function __construct(
        public int $personAccountId,
        public int $organizationAccountId,
        public string $functionCode,
        public ?int $createdBy,
    ) {
    }
}
