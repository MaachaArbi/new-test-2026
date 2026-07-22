<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Assignation de rôle introuvable (lookup Application avant revoke).
 */
final class PartyAccountRoleAssignmentNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_role.assignment_not_found';
    }

    public static function forId(int $assignmentId): self
    {
        return new self(
            sprintf('Party account role assignment %d not found.', $assignmentId),
            ['assignment_id' => $assignmentId],
        );
    }
}
