<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Une seule exception pour introuvable ET déjà fermée — miroir du RAISE SQL
 * cash_close_session ("Session % introuvable ou déjà fermée").
 */
final class CashSessionNotFoundOrAlreadyClosedException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_session.not_found_or_already_closed';
    }

    public static function forId(int $sessionId): self
    {
        return new self(
            sprintf('Cash session %d was not found or is already closed.', $sessionId),
            ['session_id' => $sessionId],
        );
    }
}
