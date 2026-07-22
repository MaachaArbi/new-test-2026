<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingUnknownChargeTypeException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.unknown_charge_type';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Unknown booking charge type code "%s".', $code),
            ['field' => 'chargeTypeCode', 'code' => $code],
        );
    }
}
