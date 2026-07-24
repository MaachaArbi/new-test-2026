<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashReceiveInstrumentNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_receive.instrument_not_found';
    }

    public static function forId(int $instrumentId): self
    {
        return new self(
            sprintf('Settlement instrument %d was not found for cash receive.', $instrumentId),
            ['instrument_id' => $instrumentId],
        );
    }
}
