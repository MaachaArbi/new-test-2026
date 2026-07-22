<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidCashPaymentMethodRoutingException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_payment_method_routing.inconsistent_tracking';
    }

    public static function inconsistentTracking(
        string $routingTypeCode,
        string $instrumentTrackingMode,
    ): self {
        return new self(
            sprintf(
                'Routing type "%s" is inconsistent with tracking mode "%s" (chk_routing_tracking_consistency).',
                $routingTypeCode,
                $instrumentTrackingMode,
            ),
            [
                'routing_type_code' => $routingTypeCode,
                'instrument_tracking_mode' => $instrumentTrackingMode,
            ],
        );
    }
}
