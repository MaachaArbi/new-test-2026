<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidSettlementInstrumentStatusException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_instrument.invalid_status';
    }

    public static function forValue(string $statusCode): self
    {
        return new self(
            sprintf('Invalid instrument status "%s".', $statusCode),
            ['status_code' => $statusCode],
        );
    }
}
