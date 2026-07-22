<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\CreateReglementMatching;

final readonly class CreateReglementMatchingCommand
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
