<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\TransitionReglementInstrumentStatus;

/**
 * Transition de status_code d'un instrument.
 */
final readonly class TransitionReglementInstrumentStatusCommand
{
    public function __construct(
        public int $instrumentId,
        public string $statusCode,
        public ?string $reason = null,
    ) {
    }
}
