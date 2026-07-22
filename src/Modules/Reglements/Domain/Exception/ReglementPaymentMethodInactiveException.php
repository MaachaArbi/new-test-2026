<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ReglementPaymentMethodInactiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_payment_method.inactive_or_unknown';
    }

    public static function forId(int $paymentMethodId): self
    {
        return new self(
            sprintf('Payment method id %d is unknown or inactive.', $paymentMethodId),
            ['payment_method_id' => $paymentMethodId],
        );
    }
}
