<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Compte Party introuvable (lookup Application avant écriture d'extension).
 */
final class PartyAccountNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account.not_found';
    }

    public static function forId(int $accountId): self
    {
        return new self(
            sprintf('Party account %d not found.', $accountId),
            ['account_id' => $accountId],
        );
    }

    public static function forPublicId(string $publicId): self
    {
        return new self(
            sprintf('Party account with public_id %s not found.', $publicId),
            ['public_id' => $publicId],
        );
    }
}
