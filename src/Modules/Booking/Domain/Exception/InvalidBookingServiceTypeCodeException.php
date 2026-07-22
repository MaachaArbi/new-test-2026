<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Code de type de service invalide (référentiel ouvert booking_service_type).
 */
final class InvalidBookingServiceTypeCodeException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_service_type_code.invalid';
    }

    public static function empty(): self
    {
        return new self(
            'Booking service type code must not be empty.',
            ['reason' => 'empty'],
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        return new self(
            sprintf('Booking service type code exceeds maximum length of %d.', $maxLength),
            [
                'reason' => 'too_long',
                'value' => $value,
                'max_length' => $maxLength,
                'length' => strlen($value),
            ],
        );
    }
}
