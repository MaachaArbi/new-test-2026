<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Assignation de fonction introuvable (lookup Application avant revoke).
 */
final class PartyAccountFunctionAssignmentNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_function.assignment_not_found';
    }

    public static function forId(int $assignmentId): self
    {
        return new self(
            sprintf('Party account function assignment %d not found.', $assignmentId),
            ['assignment_id' => $assignmentId],
        );
    }
}
