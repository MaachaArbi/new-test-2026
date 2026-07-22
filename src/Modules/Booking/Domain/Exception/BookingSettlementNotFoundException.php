<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingSettlementNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_settlement.not_found';
    }

    public static function forId(int $settlementId): self
    {
        return new self(
            sprintf('Booking settlement %d was not found.', $settlementId),
            ['settlement_id' => $settlementId],
        );
    }
}
