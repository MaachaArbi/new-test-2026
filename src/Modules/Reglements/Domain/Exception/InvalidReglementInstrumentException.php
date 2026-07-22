<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants sur l'agrégat ReglementInstrument
 * (miroir CHECK amount_minor > 0).
 */
final class InvalidReglementInstrumentException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_instrument.invalid_amount';
    }

    public static function amountMustBePositive(int $amountMinor): self
    {
        return new self(
            'Instrument amount_minor must be greater than 0.',
            ['amount_minor' => $amountMinor],
        );
    }
}
