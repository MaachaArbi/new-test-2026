<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\CreateSettlementMatching;

final readonly class CreateSettlementMatchingCommand
{
    public function __construct(
        public int $debitEntryId,
        public int $creditEntryId,
        public int $matchedAmountMinor,
        public bool $isAutomatic = false,
        public ?string $matchGroup = null,
        public ?int $matchedBy = null,
    ) {
    }
}
