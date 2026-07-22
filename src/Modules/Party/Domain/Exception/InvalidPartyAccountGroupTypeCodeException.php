<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code de type de groupe invalide (référentiel ouvert party_account_group_type).
 */
final class InvalidPartyAccountGroupTypeCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_group_type_code.invalid';
    }

    public static function empty(): self
    {
        return new self(
            message: 'Party account group type code must not be empty.',
            context: ['reason' => 'empty'],
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        return new self(
            message: sprintf('Party account group type code exceeds maximum length of %d.', $maxLength),
            context: [
                'length' => strlen($value),
                'max_length' => $maxLength,
                'reason' => 'too_long',
                'value' => $value,
            ],
        );
    }
}
