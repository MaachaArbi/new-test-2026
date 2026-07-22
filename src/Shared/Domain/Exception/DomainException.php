<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Base des exceptions métier (tous modules).
 *
 * Prépare errorCode() pour une future traduction / catalogue d'erreurs ;
 * le contexte est destiné aux logs structurés (jamais de secrets).
 */
abstract class DomainException extends \DomainException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        private readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Code stable machine-readable (ex. party_account.invalid_email).
     */
    abstract public function errorCode(): string;
}
