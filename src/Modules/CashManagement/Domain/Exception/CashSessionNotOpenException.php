<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Session absente ou statut ≠ open (closed / validated / autre).
 *
 * Application-only — le trigger SQL n'interdit que 'validated'.
 */
final class CashSessionNotOpenException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_session.not_open';
    }

    public static function forId(int $sessionId, ?string $statusCode = null): self
    {
        return new self(
            sprintf(
                'Cash session %d is not open%s.',
                $sessionId,
                $statusCode === null ? '' : sprintf(' (status=%s)', $statusCode),
            ),
            ['session_id' => $sessionId, 'status_code' => $statusCode],
        );
    }
}
