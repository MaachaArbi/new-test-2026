<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\RevokeBookingPayerSplit;

final readonly class RevokeBookingPayerSplitCommand
{
    public function __construct(
        public int $splitId,
    ) {
    }
}
