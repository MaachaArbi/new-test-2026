<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\CreatePartyAccount;

/**
 * Commande de création d'un party_account (person | organization).
 */
final readonly class CreatePartyAccountCommand
{
    public function __construct(
        public string $nature,
        public string $displayName,
        public ?string $email,
        public ?int $parentAccountId,
    ) {
    }
}
