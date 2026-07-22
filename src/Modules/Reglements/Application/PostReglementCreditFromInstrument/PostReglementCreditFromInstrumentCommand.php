<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\PostReglementCreditFromInstrument;

final readonly class PostReglementCreditFromInstrumentCommand
{
    public function __construct(
        public int $instrumentId,
        public ?int $createdBy = null,
    ) {
    }
}
