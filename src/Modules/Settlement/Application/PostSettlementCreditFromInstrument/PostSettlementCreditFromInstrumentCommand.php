<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\PostSettlementCreditFromInstrument;

final readonly class PostSettlementCreditFromInstrumentCommand
{
    public function __construct(
        public int $instrumentId,
        public ?int $createdBy = null,
    ) {
    }
}
