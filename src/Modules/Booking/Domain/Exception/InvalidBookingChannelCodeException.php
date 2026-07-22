<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code de canal invalide (référentiel ouvert booking_channel).
 */
final class InvalidBookingChannelCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_channel_code.invalid';
    }

    public static function empty(): self
    {
        return new self(
            'Booking channel code must not be empty.',
            ['reason' => 'empty'],
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        return new self(
            sprintf('Booking channel code exceeds maximum length of %d.', $maxLength),
            [
                'reason' => 'too_long',
                'value' => $value,
                'max_length' => $maxLength,
                'length' => strlen($value),
            ],
        );
    }
}
