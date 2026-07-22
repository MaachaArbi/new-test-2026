<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Tentative d'assigner un rôle déjà actif pour le même compte
 * (règle métier Application ; la contrainte DB n'est qu'un filet).
 */
final class PartyAccountRoleAlreadyActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_role.already_active';
    }

    public static function forAccountAndRole(int $accountId, string $roleCode): self
    {
        return new self(
            sprintf(
                'Account %d already has an active assignment for role "%s".',
                $accountId,
                $roleCode,
            ),
            [
                'account_id' => $accountId,
                'role_code' => $roleCode,
            ],
        );
    }
}
