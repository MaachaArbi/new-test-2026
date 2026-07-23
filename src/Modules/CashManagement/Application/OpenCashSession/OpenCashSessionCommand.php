<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\OpenCashSession;

final readonly class OpenCashSessionCommand
{
    public function __construct(
        public int $holderAccountId,
        public ?int $officeAccountId = null,
        public ?int $openedBy = null,
    ) {
    }
}
