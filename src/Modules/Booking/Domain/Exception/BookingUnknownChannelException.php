<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingUnknownChannelException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.unknown_channel';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Unknown booking channel code "%s".', $code),
            ['field' => 'channelCode', 'code' => $code],
        );
    }
}
