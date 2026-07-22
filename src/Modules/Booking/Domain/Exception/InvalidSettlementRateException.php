<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Format invalide pour SettlementRate (NUMERIC(6,3)).
 */
final class InvalidSettlementRateException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_settlement.invalid_rate';
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf('Invalid settlement rate format: "%s".', $value),
            ['value' => $value],
        );
    }
}
