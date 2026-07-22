<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Levée lorsque deux montants Money de devises différentes sont combinés,
 * ou lorsqu'un montant n'est pas cohérent avec la devise attendue.
 */
final class CurrencyMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'money.currency_mismatch';
    }

    public static function cannotAdd(string $leftCurrency, string $rightCurrency): self
    {
        return new self(
            sprintf(
                'Cannot add Money amounts with different currencies: "%s" vs "%s".',
                $leftCurrency,
                $rightCurrency,
            ),
            [
                'left_currency' => $leftCurrency,
                'right_currency' => $rightCurrency,
            ],
        );
    }

    public static function amountDoesNotMatchExpected(
        string $field,
        string $expectedCurrency,
        string $actualCurrency,
    ): self {
        return new self(
            sprintf(
                'Money field "%s" currency "%s" does not match expected "%s".',
                $field,
                $actualCurrency,
                $expectedCurrency,
            ),
            [
                'field' => $field,
                'expected_currency' => $expectedCurrency,
                'actual_currency' => $actualCurrency,
            ],
        );
    }
}
