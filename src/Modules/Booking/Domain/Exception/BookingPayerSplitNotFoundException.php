<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingPayerSplitNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_payer_split.not_found';
    }

    public static function forId(int $splitId): self
    {
        return new self(
            sprintf('Booking payer split %d was not found.', $splitId),
            ['split_id' => $splitId],
        );
    }
}
