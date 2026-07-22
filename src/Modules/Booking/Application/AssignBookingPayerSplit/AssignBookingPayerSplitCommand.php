<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AssignBookingPayerSplit;

final readonly class AssignBookingPayerSplitCommand
{
    public function __construct(
        public int $bookingId,
        public int $payerAccountId,
        public int $amountMinor,
        public string $currencyCode,
        public ?int $createdBy = null,
    ) {
    }
}
