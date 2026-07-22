<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Validation commune des littéraux décimaux string pour colonnes NUMERIC(p,s).
 *
 * Jamais float. Strictement positif (pas zéro, pas négatif) — aligné sur
 * ExchangeRate et les taux métier (commission, change).
 *
 * Règle : au plus (precision - scale) chiffres avant la virgule, au plus
 * scale après ; total des chiffres significatifs ≤ precision.
 */
final class NumericDecimal
{
    private function __construct()
    {
    }

    public static function isValidPositive(string $value, int $precision, int $scale): bool
    {
        if ($precision < 1 || $scale < 0 || $scale >= $precision) {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        $integerDigits = $precision - $scale;
        $pattern = sprintf(
            '/^(?:0\.\d{1,%d}|[1-9]\d{0,%d}(?:\.\d{1,%d})?)$/',
            $scale,
            $integerDigits - 1,
            $scale,
        );

        return preg_match($pattern, $trimmed) === 1;
    }
}
