<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\DeletePartyAccount;

/**
 * Commande de soft-delete d'un party_account.
 */
final readonly class DeletePartyAccountCommand
{
    public function __construct(
        public string $publicId,
    ) {
    }
}
