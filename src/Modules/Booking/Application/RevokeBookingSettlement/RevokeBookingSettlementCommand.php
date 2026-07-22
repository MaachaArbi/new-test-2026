<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\RevokeBookingSettlement;

final readonly class RevokeBookingSettlementCommand
{
    public function __construct(
        public int $settlementId,
    ) {
    }
}
