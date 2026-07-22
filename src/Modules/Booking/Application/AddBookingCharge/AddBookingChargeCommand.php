<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingCharge;

use App\Shared\Domain\ValueObject\Money;

final readonly class AddBookingChargeCommand
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $bookingId,
        public string $chargeTypeCode,
        public Money $achatAmount,
        public Money $venteAmount,
        public ?int $travelerId = null,
        public ?int $segmentId = null,
        public ?string $label = null,
        public array $metadata = [],
        public int $sortOrder = 0,
    ) {
    }
}
