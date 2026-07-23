<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\CloseCashSession;

final readonly class CloseCashSessionCommand
{
    public function __construct(
        public int $sessionId,
        public ?int $closedBy = null,
    ) {
    }
}
