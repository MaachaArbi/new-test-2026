<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Levée par le VO Money (code devise invalide — 3 lettres).
 */
final class InvalidCurrencyCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'money.invalid_currency_code';
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf('Invalid currency code: "%s" (expected 3 letters).', $value),
            ['value' => $value],
        );
    }
}
