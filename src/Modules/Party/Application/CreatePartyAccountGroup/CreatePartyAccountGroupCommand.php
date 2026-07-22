<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\CreatePartyAccountGroup;

/**
 * Commande de création d'un party_account_group.
 */
final readonly class CreatePartyAccountGroupCommand
{
    public function __construct(
        public string $groupTypeCode,
        public string $name,
    ) {
    }
}
