<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\UpdateCashPaymentMethodRouting;

final readonly class UpdateCashPaymentMethodRoutingCommand
{
    public function __construct(
        public int $paymentMethodId,
        public string $routingTypeCode,
        public string $instrumentTrackingMode,
        public bool $strictSourceIsolation,
        public bool $requiresCustodyCheck,
        public bool $isActive,
    ) {
    }
}
