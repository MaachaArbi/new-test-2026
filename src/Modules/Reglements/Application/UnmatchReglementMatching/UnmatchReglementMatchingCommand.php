<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\UnmatchReglementMatching;

final readonly class UnmatchReglementMatchingCommand
{
    public function __construct(
        public int $matchingId,
        public ?int $unmatchedBy = null,
    ) {
    }
}
