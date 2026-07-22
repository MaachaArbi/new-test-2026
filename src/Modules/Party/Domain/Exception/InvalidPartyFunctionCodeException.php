<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code de fonction invalide (référentiel ouvert party_function — pas un enum PHP).
 */
final class InvalidPartyFunctionCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_function_code.invalid';
    }

    public static function empty(): self
    {
        return new self(
            message: 'Party function code must not be empty.',
            context: ['reason' => 'empty'],
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        $context = [
            'length' => strlen($value),
            'max_length' => $maxLength,
            'reason' => 'too_long',
            'value' => $value,
        ];

        return new self(
            message: sprintf('Party function code exceeds maximum length of %d.', $maxLength),
            context: $context,
        );
    }
}
