<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\CreateCashPaymentMethodRouting;

final readonly class CreateCashPaymentMethodRoutingCommand
{
    public function __construct(
        public int $paymentMethodId,
        public string $routingTypeCode,
        public string $instrumentTrackingMode,
        public bool $strictSourceIsolation,
        public bool $requiresCustodyCheck = true,
        public bool $isActive = true,
    ) {
    }
}
