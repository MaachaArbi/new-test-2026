<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Levée par le VO Email Shared (format invalide) — pas spécifique à un module.
 */
final class InvalidEmailException extends DomainException
{
    public function errorCode(): string
    {
        return 'email.invalid_format';
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf('Invalid email format: "%s".', $value),
            ['value' => $value],
        );
    }
}
