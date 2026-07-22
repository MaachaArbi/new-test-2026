<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingUnknownCurrencyException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.unknown_currency';
    }

    public static function forCode(string $field, string $code): self
    {
        return new self(
            sprintf('Unknown currency code "%s" for %s.', $code, $field),
            ['field' => $field, 'code' => $code],
        );
    }
}
