<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Tentative d'assigner une fonction déjà active pour le même triplet
 * (person, organization, function_code) — règle métier Application ;
 * la contrainte DB n'est qu'un filet.
 */
final class PartyAccountFunctionAlreadyActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_function.already_active';
    }

    public static function forTriplet(
        int $personAccountId,
        int $organizationAccountId,
        string $functionCode,
    ): self {
        return new self(
            sprintf(
                'Person %d already has an active assignment for function "%s" in organization %d.',
                $personAccountId,
                $functionCode,
                $organizationAccountId,
            ),
            [
                'person_account_id' => $personAccountId,
                'organization_account_id' => $organizationAccountId,
                'function_code' => $functionCode,
            ],
        );
    }
}
