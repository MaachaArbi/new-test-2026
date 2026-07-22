<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\SetPartyAccountOrganizationIdentity;

/**
 * Pose l'extension organization_identity d'un compte organisation.
 */
final readonly class SetPartyAccountOrganizationIdentityCommand
{
    public function __construct(
        public int $accountId,
        public ?string $taxId,
        public ?string $tradeRegister,
        public ?string $legalFormCode,
        public bool $isVatSubject,
        public ?string $website,
    ) {
    }
}
