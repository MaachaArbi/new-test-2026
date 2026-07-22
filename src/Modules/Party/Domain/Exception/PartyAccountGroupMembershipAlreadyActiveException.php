<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Tentative d'assigner une appartenance déjà active pour la même paire
 * (account_id, group_id) — règle Application ; la contrainte DB n'est qu'un filet.
 */
final class PartyAccountGroupMembershipAlreadyActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_group_member.already_active';
    }

    public static function forAccountAndGroup(int $accountId, int $groupId): self
    {
        return new self(
            sprintf(
                'Account %d already has an active membership in group %d.',
                $accountId,
                $groupId,
            ),
            [
                'account_id' => $accountId,
                'group_id' => $groupId,
            ],
        );
    }
}
