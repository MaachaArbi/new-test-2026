<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AssignBookingSettlement;

use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;

final readonly class AssignBookingSettlementCommand
{
    public function __construct(
        public int $bookingId,
        public int $beneficiaryAccountId,
        public BeneficiaryRole $beneficiaryRole,
        public int $amountOwedMinor,
        public string $currencyCode,
        public int $amountSettledDirectMinor = 0,
        public ?string $rate = null,
        public ?int $resalePriceAmountMinor = null,
        public ?int $createdBy = null,
    ) {
    }
}
