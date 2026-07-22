<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Extension 1-1 réservée aux comptes nature=organization.
 */
final class PartyAccountMustBeOrganizationException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account.must_be_organization';
    }

    public static function forAccount(int $accountId, string $nature): self
    {
        return new self(
            sprintf(
                'Party account %d must be an organization (got nature "%s").',
                $accountId,
                $nature,
            ),
            [
                'account_id' => $accountId,
                'nature' => $nature,
            ],
        );
    }
}
