<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\Exception\InvalidSettlementRateException;
use App\Shared\Domain\ValueObject\NumericDecimal;

/**
 * Taux d'origine d'un settlement (ex. commission %) — NUMERIC(6,3).
 *
 * String jamais float. Précision distincte d'ExchangeRate (14,6) : validation
 * via NumericDecimal partagé, pas une regex dupliquée.
 */
final readonly class SettlementRate
{
    private const PRECISION = 6;

    private const SCALE = 3;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if (!NumericDecimal::isValidPositive($trimmed, self::PRECISION, self::SCALE)) {
            throw InvalidSettlementRateException::invalidFormat($value);
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
