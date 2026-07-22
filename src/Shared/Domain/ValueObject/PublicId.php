<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Identifiant public exposable (ADR-018) — UUID v4.
 */
final readonly class PublicId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !Uuid::isValid($trimmed)) {
            throw new InvalidArgumentException(sprintf('Invalid UUID format: "%s".', $value));
        }

        return new self(strtolower($trimmed));
    }

    public function toString(): string
    {
        return $this->value;
    }
}
