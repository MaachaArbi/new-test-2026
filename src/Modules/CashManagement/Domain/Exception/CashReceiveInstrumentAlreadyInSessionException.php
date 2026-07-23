<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashReceiveInstrumentAlreadyInSessionException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_receive.instrument_already_in_session';
    }

    public static function forSessionAndInstrument(int $sessionId, int $instrumentId): self
    {
        return new self(
            sprintf(
                'Instrument %d is already received in cash session %d.',
                $instrumentId,
                $sessionId,
            ),
            ['session_id' => $sessionId, 'instrument_id' => $instrumentId],
        );
    }
}
