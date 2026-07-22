<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashPaymentMethodRoutingNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_payment_method_routing.not_found';
    }

    public static function forPaymentMethodId(int $paymentMethodId): self
    {
        return new self(
            sprintf('Routing for payment_method_id %d was not found.', $paymentMethodId),
            ['payment_method_id' => $paymentMethodId],
        );
    }
}
