<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingUnknownServiceTypeException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.unknown_service_type';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Unknown booking service type code "%s".', $code),
            ['field' => 'serviceTypeCode', 'code' => $code],
        );
    }
}
