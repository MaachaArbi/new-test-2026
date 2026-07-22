<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\UpdatePartyAccount;

/**
 * Commande de mise à jour partielle d'un party_account (PATCH).
 */
final readonly class UpdatePartyAccountCommand
{
    public function __construct(
        public string $publicId,
        public bool $hasDisplayName,
        public ?string $displayName,
        public bool $hasIsDisabled,
        public ?bool $isDisabled,
    ) {
    }
}
