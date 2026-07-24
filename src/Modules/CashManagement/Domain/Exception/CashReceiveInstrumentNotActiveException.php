<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashReceiveInstrumentNotActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_receive.instrument_not_active';
    }

    public static function forId(int $instrumentId, string $statusCode): self
    {
        return new self(
            sprintf('Settlement instrument %d is not active (status=%s).', $instrumentId, $statusCode),
            ['instrument_id' => $instrumentId, 'status_code' => $statusCode],
        );
    }
}
