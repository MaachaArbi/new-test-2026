<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidReglementInstrumentStatusException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_instrument.invalid_status';
    }

    public static function forValue(string $statusCode): self
    {
        return new self(
            sprintf('Invalid instrument status "%s".', $statusCode),
            ['status_code' => $statusCode],
        );
    }
}
