<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidEmailException;

/**
 * Value Object email générique — validation de format (réutilisable multi-modules).
 * Égalité insensible à la casse.
 */
final readonly class Email
{
    /**
     * Pattern unique du projet — aussi utilisé par #[Assert\Regex] sur les DTO HTTP
     * (CreatePartyAccountRequest) pour éviter toute divergence DTO / Domain.
     */
    public const FORMAT_PATTERN = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match(self::FORMAT_PATTERN, $trimmed) !== 1) {
            throw InvalidEmailException::invalidFormat($value);
        }

        return new self($trimmed);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }
}
