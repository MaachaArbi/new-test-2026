<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Levée par le VO ExchangeRate (format décimal invalide).
 */
final class InvalidExchangeRateException extends DomainException
{
    public function errorCode(): string
    {
        return 'exchange_rate.invalid_format';
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf('Invalid exchange rate format: "%s".', $value),
            ['value' => $value],
        );
    }
}
