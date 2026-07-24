<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SettlementInstrumentNotActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_instrument.not_active';
    }

    public static function forId(int $instrumentId, string $statusCode): self
    {
        return new self(
            sprintf('Instrument %d is not active (status="%s").', $instrumentId, $statusCode),
            ['instrument_id' => $instrumentId, 'status_code' => $statusCode],
        );
    }
}
