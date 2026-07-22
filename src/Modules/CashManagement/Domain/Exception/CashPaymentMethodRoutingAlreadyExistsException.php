<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class CashPaymentMethodRoutingAlreadyExistsException extends DomainException
{
    public function errorCode(): string
    {
        return 'cash_payment_method_routing.already_exists';
    }

    public static function forPaymentMethodId(int $paymentMethodId): self
    {
        return new self(
            sprintf('Routing already exists for payment_method_id %d.', $paymentMethodId),
            ['payment_method_id' => $paymentMethodId],
        );
    }
}
