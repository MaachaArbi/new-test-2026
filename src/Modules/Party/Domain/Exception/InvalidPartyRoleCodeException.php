<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code de rôle invalide (référentiel ouvert party_role — pas un enum PHP).
 */
final class InvalidPartyRoleCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_role_code.invalid';
    }

    public static function empty(): self
    {
        return new self(
            'Party role code must not be empty.',
            ['reason' => 'empty'],
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        return new self(
            sprintf('Party role code exceeds maximum length of %d.', $maxLength),
            [
                'reason' => 'too_long',
                'value' => $value,
                'max_length' => $maxLength,
                'length' => strlen($value),
            ],
        );
    }
}
