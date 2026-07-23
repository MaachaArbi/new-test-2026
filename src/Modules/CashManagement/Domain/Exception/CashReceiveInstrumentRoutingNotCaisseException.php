<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashReceiveInstrumentRoutingNotCaisseException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_receive.routing_not_caisse';
    }

    public static function forPaymentMethod(int $paymentMethodId, ?string $routingTypeCode): self
    {
        return new self(
            sprintf(
                'Payment method %d is not routed to caisse (routing=%s).',
                $paymentMethodId,
                $routingTypeCode ?? 'missing',
            ),
            [
                'payment_method_id' => $paymentMethodId,
                'routing_type_code' => $routingTypeCode,
            ],
        );
    }
}
