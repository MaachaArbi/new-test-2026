<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\UnmatchSettlementMatching;

final readonly class UnmatchSettlementMatchingCommand
{
    public function __construct(
        public int $matchingId,
        public ?int $unmatchedBy = null,
    ) {
    }
}
