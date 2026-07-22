<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidExchangeRateException;

/**
 * Taux de change — stocké en string (jamais float) pour coller à NUMERIC(14,6).
 *
 * Validation via NumericDecimal (précision/échelle) — partagé avec SettlementRate.
 */
final readonly class ExchangeRate
{
    private const PRECISION = 14;

    private const SCALE = 6;

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if (!NumericDecimal::isValidPositive($trimmed, self::PRECISION, self::SCALE)) {
            throw InvalidExchangeRateException::invalidFormat($value);
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
