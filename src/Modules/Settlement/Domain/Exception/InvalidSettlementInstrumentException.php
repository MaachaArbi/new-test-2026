<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants sur l'agrégat SettlementInstrument
 * (miroir CHECK amount_minor > 0).
 */
final class InvalidSettlementInstrumentException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_instrument.invalid_amount';
    }

    public static function amountMustBePositive(int $amountMinor): self
    {
        return new self(
            'Instrument amount_minor must be greater than 0.',
            ['amount_minor' => $amountMinor],
        );
    }
}
