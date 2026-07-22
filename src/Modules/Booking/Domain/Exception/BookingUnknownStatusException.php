<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingUnknownStatusException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.unknown_status';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Unknown booking status code "%s".', $code),
            ['field' => 'statusCode', 'code' => $code],
        );
    }
}
