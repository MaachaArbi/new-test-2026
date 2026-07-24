<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus;

/**
 * Transition de status_code d'un instrument.
 */
final readonly class TransitionSettlementInstrumentStatusCommand
{
    public function __construct(
        public int $instrumentId,
        public string $statusCode,
        public ?string $reason = null,
    ) {
    }
}
