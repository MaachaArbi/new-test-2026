<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * office_code déjà utilisé (unicité globale uq_party_account_office_code).
 */
final class PartyAccountOfficeCodeAlreadyUsedException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_office.code_already_used';
    }

    public static function forCode(string $officeCode): self
    {
        return new self(
            sprintf('Office code "%s" is already used.', $officeCode),
            ['office_code' => $officeCode],
        );
    }
}
