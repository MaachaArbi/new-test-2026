<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\CurrencyMismatchException;
use App\Shared\Domain\Exception\InvalidCurrencyCodeException;

/**
 * Montant en unités mineures brutes (centimes ou équivalent selon la devise).
 *
 * Pas de résolution dynamique de minor_unit (ref_currency non modélisé Domain) :
 * la conversion affichage (unités mineures → unité majeure) est hors périmètre.
 */
final readonly class Money
{
    private function __construct(
        private int $amount,
        private string $currencyCode,
    ) {
    }

    public static function fromMinorUnits(int $amount, string $currencyCode): self
    {
        $normalized = strtoupper(trim($currencyCode));
        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw InvalidCurrencyCodeException::invalidFormat($currencyCode);
        }

        return new self($amount, $normalized);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function add(self $other): self
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw CurrencyMismatchException::cannotAdd($this->currencyCode, $other->currencyCode);
        }

        return new self($this->amount + $other->amount, $this->currencyCode);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currencyCode === $other->currencyCode;
    }
}
