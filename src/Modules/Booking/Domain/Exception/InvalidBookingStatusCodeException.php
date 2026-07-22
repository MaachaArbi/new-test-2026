<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code de statut invalide (référentiel ouvert booking_status).
 */
final class InvalidBookingStatusCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_status_code.invalid';
    }

    public static function empty(): self
    {
        return new self(
            'Booking status code must not be empty.',
            ['reason' => 'empty'],
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        return new self(
            sprintf('Booking status code exceeds maximum length of %d.', $maxLength),
            [
                'reason' => 'too_long',
                'value' => $value,
                'max_length' => $maxLength,
                'length' => strlen($value),
            ],
        );
    }
}
