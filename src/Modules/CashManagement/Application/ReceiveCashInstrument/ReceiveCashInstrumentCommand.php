<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\ReceiveCashInstrument;

final readonly class ReceiveCashInstrumentCommand
{
    public function __construct(
        public int $sessionId,
        public int $instrumentId,
        public ?int $receivedBy = null,
    ) {
    }
}
