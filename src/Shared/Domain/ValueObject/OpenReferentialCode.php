<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code string d'un référentiel ouvert : trim, rejet du vide, rejet si trop long.
 * Chaque sous-classe fixe maxLength() et lève ses propres DomainException.
 */
abstract readonly class OpenReferentialCode
{
    final protected function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): static
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw static::emptyException();
        }

        $maxLength = static::maxLength();
        if (strlen($normalized) > $maxLength) {
            throw static::tooLongException($normalized, $maxLength);
        }

        return new static($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    abstract protected static function maxLength(): int;

    abstract protected static function emptyException(): DomainException;

    abstract protected static function tooLongException(string $value, int $maxLength): DomainException;
}
