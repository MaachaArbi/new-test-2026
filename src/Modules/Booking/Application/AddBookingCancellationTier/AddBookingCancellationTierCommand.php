<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingCancellationTier;

use App\Modules\Booking\Domain\ValueObject\PenaltyType;

final readonly class AddBookingCancellationTierCommand
{
    public function __construct(
        public int $policyId,
        public int $daysBeforeStart,
        public PenaltyType $penaltyType,
        public ?string $penaltyValue = null,
        public ?string $thresholdTime = null,
        public ?int $minStayNights = null,
        public ?int $maxStayNights = null,
        public int $sortOrder = 0,
    ) {
    }
}
